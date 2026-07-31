# HumanVerify

> A lightweight, self-hosted human verification (CAPTCHA) system with slider, click-select, and math challenge modes. Includes a Turnstile-style compact widget, PHP + MySQL backend, and admin dashboard.
>
> 一套轻量级、可自部署的人机验证（CAPTCHA）系统，支持滑块、点选、数学题三种验证方式，包含 Turnstile 风格紧凑模式组件、PHP + MySQL 后端及管理后台。

---

## 目录 / Table of Contents

- [中文文档](#中文文档)
  - [功能特性](#功能特性)
  - [技术栈](#技术栈)
  - [目录结构](#目录结构)
  - [快速开始](#快速开始)
  - [前端接入](#前端接入)
  - [后端校验](#后端校验)
  - [管理后台](#管理后台)
  - [配置项](#配置项)
- [English Documentation](#english-documentation)
  - [Features](#features)
  - [Tech Stack](#tech-stack)
  - [Directory Structure](#directory-structure)
  - [Quick Start](#quick-start)
  - [Frontend Integration](#frontend-integration)
  - [Backend Verification](#backend-verification)
  - [Admin Panel](#admin-panel)
  - [Configuration](#configuration)
- [License](#license)

---

# 中文文档

## 功能特性

- ✅ **三种验证类型**：滑块拼图、点选定位、数学计算
- ✅ **紧凑模式组件**（类 Cloudflare Turnstile 交互）：360×80px 组件，点击触发弹窗验证
- ✅ **三种难度等级**：简单、普通、困难
- ✅ **完整后端 API**：验证码生成、校验、Token 一次性消费
- ✅ **管理后台**：登录鉴权、系统配置、验证日志、统计面板
- ✅ **MySQL 存储**：支持高并发场景
- ✅ **跨域支持**：可通过 `apiBase` 接入任意第三方网站
- ✅ **PHP SDK**：便于后端快速集成 Token 二次校验

## 技术栈

| 层 | 技术 |
|---|---|
| 前端 SDK | 原生 JavaScript（无依赖） |
| 后端 API | PHP 8.0+ / PDO |
| 数据库 | MySQL 5.7+（兼容 SQLite 降级方案） |
| 样式 | 原生 CSS |

## 目录结构

```
HumanVerify/
├── api/                     # 后端 API
│   ├── captcha.php          # 验证码生成
│   ├── verify.php           # 验证码校验
│   ├── auth.php             # 管理员登录
│   ├── check-token.php      # Token 二次校验
│   └── admin/               # 管理后台 API
│       ├── auth_middleware.php
│       ├── config.php
│       ├── logs.php
│       └── stats.php
├── config/
│   ├── config.php           # 全局配置
│   └── database.php         # 数据库连接与建表
├── css/
│   └── style.css            # 组件样式
├── js/
│   └── captcha-sdk.js       # 前端 SDK
├── sdk/
│   └── humanverify-sdk.php  # PHP 后端 SDK
├── data/                    # SQLite 数据目录（可选）
├── index.html               # 演示页面
├── integration-demo.html    # 第三方接入演示
├── external-demo.html       # 跨域接入演示
├── admin.html               # 管理后台
├── admin-login.html         # 管理员登录
├── 1.html                   # API 测试页
└── LOGO.png
```

## 快速开始

### 1. 环境要求

- PHP 8.0 或更高版本
- MySQL 5.7 或更高版本
- 启用 PDO MySQL 扩展（`extension=php_pdo_mysql.dll`）

### 2. 数据库配置

编辑 `config/database.php`：

```php
$host = 'localhost';
$port = '3306';
$dbname = 'your_database';
$user = 'your_user';
$pass = 'your_password';
```

表结构会在首次访问时自动创建。

### 3. 启动服务

```bash
php -S 0.0.0.0:8000
```

访问 `http://localhost:8000/index.html` 即可查看演示。

### 4. 默认管理员

- 用户名：`Administrator`
- 密码：`Administrator@9000`

> ⚠️ **请务必在首次登录后修改默认密码**

## 前端接入

### 引入资源

```html
<link rel="stylesheet" href="css/style.css">
<script src="js/captcha-sdk.js"></script>
<div id="captcha-box"></div>
```

### 初始化

```javascript
const captcha = new HumanVerify({
    container: '#captcha-box',
    type: 'slider',          // slider | click | math
    difficulty: 'normal',    // easy | normal | hard
    compact: true,           // 紧凑模式（默认开启）
    apiBase: '/api/',        // 跨域时填写完整 URL
    onSuccess: (token) => {
        console.log('验证通过, token:', token);
        document.getElementById('verify_token').value = token;
    },
    onFail: (err) => {
        console.log('验证失败:', err);
    }
});
captcha.render();
```

### 表单提交时验证

```javascript
document.getElementById('myForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const result = await captcha.verify();
    if (result.success) {
        // 提交表单
    }
});
```

## 后端校验

在用户提交表单后，使用 PHP SDK 二次校验 Token，防止前端伪造：

```php
require_once 'sdk/humanverify-sdk.php';

$token = $_POST['verify_token'] ?? '';
$result = HumanVerifySDK::check('http://localhost:8000', $token);

if ($result['success']) {
    // Token 有效，处理业务逻辑
} else {
    // Token 无效或已使用
}
```

## 管理后台

访问 `admin.html`，使用默认账号登录后可：

- 🔧 修改系统配置（默认验证类型、难度、容差、过期时间等）
- 📊 查看验证统计（成功率、响应时间）
- 📋 查看验证日志
- 👤 管理员账户管理

## 配置项

| 配置项 | 说明 | 默认值 |
|---|---|---|
| `captcha_type` | 默认验证类型 | `slider` |
| `difficulty` | 默认难度 | `normal` |
| `slider_tolerance` | 滑块容差（px） | `5` |
| `click_tolerance` | 点选容差（px） | `30` |
| `session_expire` | 会话过期时间（秒） | `300` |
| `max_attempts` | 最大尝试次数 | `3` |

---

# English Documentation

## Features

- ✅ **Three verification types**: Slider puzzle, click-select, math challenge
- ✅ **Compact widget** (Turnstile-style interaction): 360×80px component, click to trigger modal verification
- ✅ **Three difficulty levels**: easy, normal, hard
- ✅ **Complete backend API**: Captcha generation, verification, one-time token consumption
- ✅ **Admin dashboard**: Auth login, system config, verify logs, statistics panel
- ✅ **MySQL storage**: Suitable for high-concurrency scenarios
- ✅ **Cross-domain support**: Connect to any third-party site via `apiBase`
- ✅ **PHP SDK**: Quick server-side token re-verification

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend SDK | Vanilla JavaScript (no dependencies) |
| Backend API | PHP 8.0+ / PDO |
| Database | MySQL 5.7+ (SQLite fallback supported) |
| Styles | Vanilla CSS |

## Directory Structure

```
HumanVerify/
├── api/                     # Backend APIs
│   ├── captcha.php          # Captcha generation
│   ├── verify.php           # Captcha verification
│   ├── auth.php             # Admin login
│   ├── check-token.php      # Token re-verification
│   └── admin/               # Admin panel APIs
│       ├── auth_middleware.php
│       ├── config.php
│       ├── logs.php
│       └── stats.php
├── config/
│   ├── config.php           # Global config
│   └── database.php         # DB connection & schema
├── css/
│   └── style.css            # Component styles
├── js/
│   └── captcha-sdk.js       # Frontend SDK
├── sdk/
│   └── humanverify-sdk.php  # PHP backend SDK
├── data/                    # SQLite data dir (optional)
├── index.html               # Demo page
├── integration-demo.html    # Integration demo
├── external-demo.html       # Cross-domain demo
├── admin.html               # Admin panel
├── admin-login.html         # Admin login
├── 1.html                   # API test page
└── LOGO.png
```

## Quick Start

### 1. Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher
- PDO MySQL extension enabled (`extension=php_pdo_mysql.dll`)

### 2. Database Configuration

Edit `config/database.php`:

```php
$host = 'localhost';
$port = '3306';
$dbname = 'your_database';
$user = 'your_user';
$pass = 'your_password';
```

Tables are created automatically on first access.

### 3. Start the Server

```bash
php -S 0.0.0.0:8000
```

Open `http://localhost:8000/index.html` to view the demo.

### 4. Default Admin

- Username: `Administrator`
- Password: `Administrator@9000`

> ⚠️ **Please change the default password after first login**

## Frontend Integration

### Include Resources

```html
<link rel="stylesheet" href="css/style.css">
<script src="js/captcha-sdk.js"></script>
<div id="captcha-box"></div>
```

### Initialization

```javascript
const captcha = new HumanVerify({
    container: '#captcha-box',
    type: 'slider',          // slider | click | math
    difficulty: 'normal',    // easy | normal | hard
    compact: true,           // compact mode (default on)
    apiBase: '/api/',        // Full URL for cross-domain
    onSuccess: (token) => {
        console.log('Verified, token:', token);
        document.getElementById('verify_token').value = token;
    },
    onFail: (err) => {
        console.log('Failed:', err);
    }
});
captcha.render();
```

### Verify on Form Submit

```javascript
document.getElementById('myForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const result = await captcha.verify();
    if (result.success) {
        // Submit form
    }
});
```

## Backend Verification

After the user submits the form, use the PHP SDK to re-verify the token to prevent frontend forgery:

```php
require_once 'sdk/humanverify-sdk.php';

$token = $_POST['verify_token'] ?? '';
$result = HumanVerifySDK::check('http://localhost:8000', $token);

if ($result['success']) {
    // Token valid, process business logic
} else {
    // Token invalid or already used
}
```

## Admin Panel

Visit `admin.html` and log in with the default account to:

- 🔧 Modify system config (default type, difficulty, tolerance, expiry, etc.)
- 📊 View verification statistics (success rate, response time)
- 📋 View verification logs
- 👤 Manage admin accounts

## Configuration

| Option | Description | Default |
|---|---|---|
| `captcha_type` | Default verification type | `slider` |
| `difficulty` | Default difficulty | `normal` |
| `slider_tolerance` | Slider tolerance (px) | `5` |
| `click_tolerance` | Click tolerance (px) | `30` |
| `session_expire` | Session expiry (seconds) | `300` |
| `max_attempts` | Max attempts | `3` |

---

## License

MIT License - feel free to use, modify, and distribute.

---

<p align="center">Made with ❤️ for the HumanVerify project</p>