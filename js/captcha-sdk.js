/**
 * HumanVerify - JS SDK
 * 
 * 用法:
 *   const captcha = new HumanVerify({
 *       container: '#captcha-box',
 *       type: 'slider',         // slider | click | math
 *       difficulty: 'normal',   // easy | normal | hard
 *       apiBase: 'https://captcha.127.0.0.1/api/',  // 跨域时指定完整URL
 *       onSuccess: (token) => { console.log('通过', token); },
 *       onFail: () => { console.log('失败'); },
 *       onError: (err) => { console.log('错误', err); }
 *   });
 *   captcha.render();
 * 
 * API 验证:
 *   const result = await captcha.verify();
 *   if (result.success) { // 提交表单
 */

(function (global) {
    'use strict';

    const API_BASE = '/api/';

    class HumanVerify {
        constructor(options = {}) {
            this.container = typeof options.container === 'string'
                ? document.querySelector(options.container)
                : options.container;
            this._origContainer = this.container;
            this.type = options.type || 'slider';
            this.difficulty = options.difficulty || 'normal';
            this.compact = options.compact !== undefined ? options.compact : true;
            this.onSuccess = options.onSuccess || (() => {});
            this.onFail = options.onFail || (() => {});
            this.onError = options.onError || (() => {});
            this.apiBase = options.apiBase || API_BASE;

            this.sessionId = null;
            this.captchaData = null;
            this.verified = false;
            this.verifyToken = null;

            // 滑块状态
            this.sliderDragging = false;
            this.sliderStartX = 0;
            this.sliderOffset = 0;

            // 紧凑模式状态
            this._modalEl = null;
            this._widgetChecked = false;
        }

        /**
         * 渲染验证码
         */
        async render() {
            if (this.compact) {
                this._renderCompactWidget();
                // 预加载验证码数据
                try {
                    const data = await this._fetchCaptcha();
                    this.captchaData = data;
                    this.sessionId = data.session_id;
                } catch (e) {
                    // 失败时在打开时重新加载
                }
                return;
            }
            this._showLoading();
            try {
                const data = await this._fetchCaptcha();
                this.captchaData = data;
                this.sessionId = data.session_id;
                this._renderByType(data);
            } catch (e) {
                this._showError('验证码加载失败: ' + e.message);
                this.onError(e);
            }
        }

        /**
         * 验证答案
         */
        async verify() {
            if (this.verified) return { success: true, token: this.verifyToken };
            if (!this.sessionId) throw new Error('请先渲染验证码');

            const answer = this._getAnswer();
            if (!answer && answer !== 0) {
                this._showError('请完成验证');
                return { success: false };
            }

            try {
                console.log('[HumanVerify] verifying: type=' + this.type + ', answer=' + answer + ', session=' + this.sessionId);
                const resp = await fetch(this.apiBase + 'verify.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: this.sessionId,
                        answer: answer
                    })
                });
                const result = await resp.json();
                console.log('[HumanVerify] verify response:', result);

                if (result.code === 200 && result.data && result.data.success) {
                    this.verified = true;
                    this.verifyToken = result.data.verify_token;
                    if (this.compact) {
                        this._widgetChecked = true;
                        this._closeModal();
                        this._updateWidgetChecked();
                    } else {
                        this._showSuccess();
                    }
                    this.onSuccess(result.data.verify_token);
                    return { success: true, token: result.data.verify_token };
                } else {
                    // 显示真实错误信息
                    const errMsg = result.message || '验证失败，请重试';
                    const debugInfo = result.data && result.data.debug
                        ? ' [答案: ' + result.data.debug.user_answer + ', 正确: ' + result.data.debug.correct_answer + ', 类型: ' + result.data.debug.type + ']'
                        : '';
                    console.log('[HumanVerify] verify failed:', errMsg + debugInfo);
                    this._showError(errMsg);
                    this.onFail(errMsg);
                    return { success: false, message: errMsg };
                }
            } catch (e) {
                this._showError('验证请求失败');
                this.onError(e);
                return { success: false, message: e.message };
            }
        }

        /**
         * 刷新验证码
         */
        async refresh() {
            this.verified = false;
            this.verifyToken = null;
            this.sessionId = null;
            this._widgetChecked = false;
            await this.render();
        }

        /**
         * 获取验证令牌
         */
        getToken() {
            return this.verifyToken;
        }

        /**
         * 销毁实例
         */
        destroy() {
            if (this.container) {
                this.container.innerHTML = '';
            }
        }

        // ========== 私有方法 ==========

        async _fetchCaptcha() {
            const resp = await fetch(
                this.apiBase + 'captcha.php?type=' + this.type + '&difficulty=' + this.difficulty
            );
            const text = await resp.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                throw new Error('API返回非JSON: ' + text.substring(0, 200));
            }
            if (result.code !== 200) throw new Error(result.message);
            return result.data;
        }

        _renderByType(data, targetEl) {
            const target = targetEl || this.container;
            switch (data.type) {
                case 'slider': this._renderSlider(data, target); break;
                case 'click': this._renderClick(data, target); break;
                case 'math': this._renderMath(data, target); break;
                default: this._showError('不支持的验证类型');
            }
        }

        // ===== 滑块验证码 =====
        _renderSlider(data, targetEl) {
            const target = targetEl || this.container;
            const totalW = data.total_width;
            const sliderW = data.slider_width;
            const maxOffset = data.max_offset;
            const bgSeed = data.bg_seed;
            const answerPos = data.answer_pos;

            const html = `
                <div class="hv-captcha hv-slider">
                    <div class="hv-slider-header">
                        <span>拖动滑块将拼图拼合到缺口处</span>
                        <button class="hv-refresh-btn" title="刷新">&#8635;</button>
                    </div>
                    <div class="hv-slider-canvas-wrap">
                        <canvas class="hv-slider-bg" width="${totalW}" height="160"></canvas>
                        <canvas class="hv-slider-puzzle" width="${sliderW}" height="160"
                            style="left:0; width:${sliderW}px; pointer-events:none;"></canvas>
                    </div>
                    <div class="hv-slider-track">
                        <div class="hv-slider-bar" style="width:${totalW}px;">
                            <div class="hv-slider-btn" style="width:${sliderW}px;">
                                <span class="hv-slider-arrow">&#10142;</span>
                            </div>
                            <div class="hv-slider-progress" style="width:0;"></div>
                        </div>
                        <div class="hv-slider-tip">向右拖动滑块完成拼图</div>
                    </div>
                    <div class="hv-status"></div>
                    <div class="hv-brand">
                        <img src="data:image/png;base64,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" alt="" class="hv-brand-icon">
                        <span class="hv-brand-text">HumanVerify</span>
                    </div>
                </div>
            `;
            this.container = target;
            this.container.innerHTML = html;

            // 绘制背景（带缺口）
            this._drawSliderBg(totalW, 160, bgSeed, answerPos, sliderW);
            // 绘制拼图块（从背景缺口位置抠出的图案）
            this._drawSliderPuzzle(sliderW, 160, bgSeed, answerPos, totalW);

            // 绑定滑块事件
            this._bindSliderEvents(totalW, sliderW, maxOffset);
            // 刷新按钮
            this.container.querySelector('.hv-refresh-btn').onclick = () => this.refresh();
        }

        /** 绘制背景图案（公共方法，确保拼图块和背景图案一致） */
        _drawPattern(ctx, w, h, seed) {
            const rng = this._seededRandom(seed);

            // 背景底色
            ctx.fillStyle = '#F0F4F8';
            ctx.fillRect(0, 0, w, h);

            // 随机几何图案
            for (let i = 0; i < 30; i++) {
                const x = rng() * w;
                const y = rng() * h;
                const s = 10 + rng() * 30;
                const colors = ['#CBD5E1', '#94A3B8', '#E2E8F0', '#BAE6FD', '#C7D2FE'];
                ctx.fillStyle = colors[Math.floor(rng() * colors.length)];
                ctx.beginPath();
                if (rng() > 0.5) {
                    ctx.arc(x, y, s / 2, 0, Math.PI * 2);
                } else {
                    ctx.fillRect(x, y, s, s);
                }
                ctx.fill();
            }

            // 文字干扰
            ctx.fillStyle = '#94A3B8';
            ctx.font = '14px Arial';
            for (let i = 0; i < 8; i++) {
                const x = rng() * w;
                const y = rng() * h;
                ctx.fillText(String.fromCharCode(65 + Math.floor(rng() * 26)), x, y);
            }
        }

        _drawSliderBg(w, h, seed, gapPos, gapW) {
            const canvas = this.container.querySelector('.hv-slider-bg');
            const ctx = canvas.getContext('2d');

            // 绘制完整背景图案
            this._drawPattern(ctx, w, h, seed);

            // 在缺口位置清除一块区域（模拟缺口）
            ctx.clearRect(gapPos, 0, gapW, h);
            // 缺口虚线边框
            ctx.save();
            ctx.strokeStyle = '#EF4444';
            ctx.lineWidth = 2;
            ctx.setLineDash([5, 4]);
            ctx.strokeRect(gapPos + 1, 1, gapW - 2, h - 2);
            ctx.restore();
        }

        _drawSliderPuzzle(w, h, seed, gapPos, totalW) {
            const canvas = this.container.querySelector('.hv-slider-puzzle');
            const ctx = canvas.getContext('2d');

            // 创建和背景同样尺寸的临时 canvas，绘制相同的图案
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = totalW;
            tempCanvas.height = h;
            const tempCtx = tempCanvas.getContext('2d');
            this._drawPattern(tempCtx, totalW, h, seed);

            // 从 gapPos 处截取 w 宽度的图案到拼图 canvas
            ctx.drawImage(tempCanvas, gapPos, 0, w, h, 0, 0, w, h);

            // 边框和阴影
            ctx.save();
            ctx.shadowColor = 'rgba(79, 70, 229, 0.4)';
            ctx.shadowBlur = 6;
            ctx.strokeStyle = '#4F46E5';
            ctx.lineWidth = 2;
            ctx.strokeRect(1, 1, w - 2, h - 2);
            ctx.restore();
        }

        _bindSliderEvents(totalW, sliderW, maxOffset) {
            const btn = this.container.querySelector('.hv-slider-btn');
            const puzzle = this.container.querySelector('.hv-slider-puzzle');
            const progress = this.container.querySelector('.hv-slider-progress');
            const tip = this.container.querySelector('.hv-slider-tip');
            const self = this;

            const getX = (e) => {
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                return clientX;
            };

            const onStart = (e) => {
                e.preventDefault();
                self.sliderDragging = true;
                self.sliderStartX = getX(e) - self.sliderOffset;
                btn.classList.add('dragging');
                tip.style.display = 'none';
            };

            const onMove = (e) => {
                if (!self.sliderDragging) return;
                e.preventDefault();
                let offset = getX(e) - self.sliderStartX;
                offset = Math.max(0, Math.min(offset, maxOffset));
                self.sliderOffset = offset;
                btn.style.transform = `translateX(${offset}px)`;
                puzzle.style.left = offset + 'px';
                progress.style.width = ((offset + sliderW) / totalW * 100) + '%';
            };

            const onEnd = (e) => {
                if (!self.sliderDragging) return;
                self.sliderDragging = false;
                btn.classList.remove('dragging');
                if (self.sliderOffset < 5) {
                    tip.style.display = '';
                }
            };

            btn.addEventListener('mousedown', onStart);
            btn.addEventListener('touchstart', onStart, { passive: false });
            document.addEventListener('mousemove', onMove);
            document.addEventListener('touchmove', onMove, { passive: false });
            document.addEventListener('mouseup', onEnd);
            document.addEventListener('touchend', onEnd);
        }

        _getSliderAnswer() {
            return this.sliderOffset;
        }

        _getModalEl() {
            return this._modalEl ? this._modalEl.querySelector('.hv-modal-body') : null;
        }

        _getMathAnswer() {
            // 紧凑模式：从模态框内查找
            if (this.compact) {
                const modal = this._getModalEl();
                if (modal) {
                    const input = modal.querySelector('.hv-math-input');
                    if (input) return input.value.trim();
                }
            }
            const input = this.container.querySelector('.hv-math-input');
            return input ? input.value.trim() : null;
        }

        // ===== 点选验证码 =====
        _renderClick(data, targetEl) {
            const target = targetEl || this.container;
            const w = data.width;
            const h = data.height;
            const bgSeed = data.bg_seed;
            const targetX = data.target_x;
            const targetY = data.target_y;

            const html = `
                <div class="hv-captcha hv-click">
                    <div class="hv-click-header">
                        <span>点击图中红色标记的目标</span>
                        <button class="hv-refresh-btn" title="刷新">&#8635;</button>
                    </div>
                    <div class="hv-click-area">
                        <canvas class="hv-click-canvas" width="${w}" height="${h}"></canvas>
                    </div>
                    <div class="hv-status"></div>
                    <div class="hv-brand">
                        <img src="data:image/png;base64,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" alt="" class="hv-brand-icon">
                        <span class="hv-brand-text">HumanVerify</span>
                    </div>
                </div>
            `;
            this.container = target;
            this.container.innerHTML = html;

            // 绘制背景
            const canvas = this.container.querySelector('.hv-click-canvas');
            const ctx = canvas.getContext('2d');
            const rng = this._seededRandom(bgSeed);

            ctx.fillStyle = '#F0F4F8';
            ctx.fillRect(0, 0, w, h);

            // 网格
            ctx.strokeStyle = '#E2E8F0';
            ctx.lineWidth = 0.5;
            for (let i = 0; i < w; i += 25) { ctx.beginPath(); ctx.moveTo(i, 0); ctx.lineTo(i, h); ctx.stroke(); }
            for (let i = 0; i < h; i += 25) { ctx.beginPath(); ctx.moveTo(0, i); ctx.lineTo(w, i); ctx.stroke(); }

            // 随机图形
            for (let i = 0; i < 20; i++) {
                const x = rng() * w;
                const y = rng() * h;
                const s = 8 + rng() * 20;
                const colors = ['#CBD5E1', '#94A3B8', '#BAE6FD', '#C7D2FE', '#DDD6FE'];
                ctx.fillStyle = colors[Math.floor(rng() * colors.length)];
                ctx.beginPath();
                ctx.arc(x, y, s, 0, Math.PI * 2);
                ctx.fill();
            }

            // 文字
            ctx.fillStyle = '#64748B';
            ctx.font = '12px Arial';
            for (let i = 0; i < 6; i++) {
                ctx.fillText(String.fromCharCode(65 + Math.floor(rng() * 26)), rng() * w, rng() * h);
            }

            // 绘制目标标记（红色靶心）
            const tol = data.tolerance || 30;
            // 外圈（半透明红）
            ctx.beginPath();
            ctx.arc(targetX, targetY, tol, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(239, 68, 68, 0.15)';
            ctx.fill();
            ctx.strokeStyle = 'rgba(239, 68, 68, 0.5)';
            ctx.lineWidth = 2;
            ctx.setLineDash([4, 4]);
            ctx.stroke();
            ctx.setLineDash([]);
            // 中心十字
            ctx.strokeStyle = '#EF4444';
            ctx.lineWidth = 2.5;
            ctx.beginPath();
            ctx.arc(targetX, targetY, 8, 0, Math.PI * 2);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(targetX - 12, targetY);
            ctx.lineTo(targetX + 12, targetY);
            ctx.moveTo(targetX, targetY - 12);
            ctx.lineTo(targetX, targetY + 12);
            ctx.stroke();
            // 中心点
            ctx.beginPath();
            ctx.arc(targetX, targetY, 3, 0, Math.PI * 2);
            ctx.fillStyle = '#EF4444';
            ctx.fill();

            // 绑定点击
            const area = this.container.querySelector('.hv-click-area');
            const self = this;
            this.clickPos = null;

            area.addEventListener('click', (e) => {
                const rect = canvas.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const scaleX = w / rect.width;
                const scaleY = h / rect.height;
                self.clickPos = {
                    x: Math.round(x * scaleX),
                    y: Math.round(y * scaleY)
                };
                // 在点击位置画一个临时标记
                const clickCtx = canvas.getContext('2d');
                clickCtx.beginPath();
                clickCtx.arc(self.clickPos.x, self.clickPos.y, 5, 0, Math.PI * 2);
                clickCtx.fillStyle = '#22C55E';
                clickCtx.fill();
                clickCtx.strokeStyle = '#16A34A';
                clickCtx.lineWidth = 2;
                clickCtx.stroke();
            });

            this.container.querySelector('.hv-refresh-btn').onclick = () => this.refresh();
        }

        _getClickAnswer() {
            return this.clickPos ? `${this.clickPos.x},${this.clickPos.y}` : null;
        }

        // ===== 数学题验证码 =====
        _renderMath(data, targetEl) {
            const target = targetEl || this.container;
            const html = `
                <div class="hv-captcha hv-math">
                    <div class="hv-math-question">
                        <span class="hv-math-label">请计算：</span>
                        <span class="hv-math-expr">${data.question}</span>
                    </div>
                    <div class="hv-math-input-wrap">
                        <input type="text" class="hv-math-input" placeholder="输入答案" autocomplete="off">
                        <button class="hv-refresh-btn" title="刷新">&#8635;</button>
                    </div>
                    <div class="hv-status"></div>
                    <div class="hv-brand">
                        <img src="data:image/png;base64,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" alt="" class="hv-brand-icon">
                        <span class="hv-brand-text">HumanVerify</span>
                    </div>
                </div>
            `;
            target.innerHTML = html;

            target.querySelector('.hv-refresh-btn').onclick = () => this.refresh();
        }

        _getMathAnswer() {
            const input = this.container.querySelector('.hv-math-input');
            return input ? input.value.trim() : null;
        }

        // ===== 通用 =====
        _getAnswer() {
            switch (this.type) {
                case 'slider': return this._getSliderAnswer();
                case 'click': return this._getClickAnswer();
                case 'math': return this._getMathAnswer();
                default: return null;
            }
        }

        _showLoading() {
            this.container.innerHTML = '<div class="hv-loading">加载验证码中...</div>';
        }

        _showSuccess() {
            const status = this.container.querySelector('.hv-status');
            if (status) {
                status.innerHTML = '<span class="hv-success">&#10003; 验证通过</span>';
            }
        }

        _showFail() {
            const status = this.container.querySelector('.hv-status');
            if (status) {
                status.innerHTML = '<span class="hv-fail">&#10007; 验证失败，请重试</span>';
            }
        }

        _showError(msg) {
            // 紧凑模式：优先显示到模态框的错误区域
            if (this.compact && this._modalEl) {
                const errorEl = this._modalEl.querySelector('.hv-modal-error');
                if (errorEl) {
                    errorEl.textContent = msg;
                    errorEl.style.display = 'block';
                    return;
                }
            }
            const status = this.container.querySelector('.hv-status');
            if (status) {
                status.innerHTML = '<span class="hv-fail">' + msg + '</span>';
            }
        }

        // ===== 紧凑模式（类 Turnstile） =====
        _renderCompactWidget() {
            const html = `
                <div class="hv-compact-widget">
                    <div class="hv-compact-check">
                        <div class="hv-compact-checkbox${this._widgetChecked ? ' checked' : ''}">
                            ${this._widgetChecked ? '<span class="hv-compact-checkmark">&#10003;</span>' : ''}
                        </div>
                        <span class="hv-compact-label">确认您是真人</span>
                    </div>
                    <div class="hv-compact-brand">
                        <img src="LOGO.png" alt="" class="hv-compact-brand-icon">
                    </div>
                </div>
            `;
            this.container.innerHTML = html;

            const self = this;
            const widget = this.container.querySelector('.hv-compact-widget');
            widget.addEventListener('click', () => {
                if (self.verified) return;
                self._openModal();
            });
        }

        _updateWidgetChecked() {
            const widget = this.container.querySelector('.hv-compact-widget');
            if (!widget) return;
            const checkbox = widget.querySelector('.hv-compact-checkbox');
            checkbox.classList.add('checked');
            checkbox.innerHTML = '<span class="hv-compact-checkmark">&#10003;</span>';
            const label = widget.querySelector('.hv-compact-label');
            if (label) label.textContent = '验证通过';
        }

        _openModal() {
            // 创建模态框
            const overlay = document.createElement('div');
            overlay.className = 'hv-modal-overlay';
            const uid = this._uid();
            overlay.innerHTML = `
                <div class="hv-modal-box">
                    <div class="hv-modal-header">
                        <span class="hv-modal-title">HumanVerify</span>
                        <button class="hv-modal-close" title="关闭">&times;</button>
                    </div>
                    <div class="hv-modal-body" id="hv-modal-body-${uid}">
                        <div class="hv-loading">加载验证码中...</div>
                    </div>
                    <div class="hv-modal-error" id="hv-modal-error-${uid}"></div>
                    <div class="hv-modal-footer">
                        <button class="hv-modal-refresh-btn" title="刷新验证码">&#8635;</button>
                        <button class="hv-modal-verify-btn">验证</button>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
            this._modalEl = overlay;
            this._modalUid = uid;

            const self = this;
            const errorEl = overlay.querySelector('.hv-modal-error');
            overlay.querySelector('.hv-modal-close').onclick = () => self._closeModal();
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) self._closeModal();
            });
            overlay.querySelector('.hv-modal-refresh-btn').onclick = async () => {
                const body = overlay.querySelector('.hv-modal-body');
                body.innerHTML = '<div class="hv-loading">刷新验证码...</div>';
                errorEl.style.display = 'none';
                try {
                    self.captchaData = null;
                    self.sessionId = null;
                    const data = await self._fetchCaptcha();
                    self.captchaData = data;
                    self.sessionId = data.session_id;
                    self._renderByType(data, body);
                } catch (e) {
                    body.innerHTML = '<div class="hv-loading" style="color:#EF4444;">刷新失败: ' + e.message + '</div>';
                }
            };
            overlay.querySelector('.hv-modal-verify-btn').onclick = async () => {
                const btn = overlay.querySelector('.hv-modal-verify-btn');
                errorEl.style.display = 'none';
                btn.disabled = true;
                btn.textContent = '验证中...';
                const result = await self.verify();
                if (!result.success) {
                    btn.disabled = false;
                    btn.textContent = '验证';
                    // 显示错误信息在模态框内
                    errorEl.textContent = result.message || '验证失败，请重试';
                    errorEl.style.display = 'block';
                    // 刷新验证码
                    const body = overlay.querySelector('.hv-modal-body');
                    body.innerHTML = '<div class="hv-loading">加载验证码中...</div>';
                    try {
                        self.captchaData = null;
                        self.sessionId = null;
                        const data = await self._fetchCaptcha();
                        self.captchaData = data;
                        self.sessionId = data.session_id;
                        self._renderByType(data, body);
                    } catch (e) {
                        body.innerHTML = '<div class="hv-loading" style="color:#EF4444;">加载失败: ' + e.message + '</div>';
                    }
                }
            };

            // 加载验证码
            const body = overlay.querySelector('.hv-modal-body');
            this._renderCaptchaInModal(body);
        }

        _closeModal() {
            if (this._modalEl) {
                this._modalEl.remove();
                this._modalEl = null;
            }
            this.container = this._origContainer;
        }

        _uid() {
            return 'hv' + Math.random().toString(36).slice(2, 8);
        }

        _renderCaptchaInModal(container) {
            const self = this;
            if (!this.captchaData || !this.sessionId) {
                // 重新获取
                this._fetchCaptcha().then(data => {
                    self.captchaData = data;
                    self.sessionId = data.session_id;
                    self._renderByType(data, container);
                }).catch(e => {
                    container.innerHTML = '<div class="hv-loading" style="color:#EF4444;">验证码加载失败: ' + e.message + '</div>';
                });
                return;
            }

            // 已有数据，直接渲染到模态框
            this._renderByType(this.captchaData, container);
        }

        _seededRandom(seed) {
            let s = seed;
            return function () {
                s = (s * 1103515245 + 12345) & 0x7fffffff;
                return s / 0x7fffffff;
            };
        }
    }

    // 静态方法：第三方 API 验证
    HumanVerify.verifyToken = async function (token, apiBase) {
        const base = apiBase || API_BASE;
        const resp = await fetch(base + 'verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: token, answer: '__token_check__' })
        });
        return await resp.json();
    };

    global.HumanVerify = HumanVerify;

})(window);
