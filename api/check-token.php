<?php
/**
 * 外部后端令牌校验 API
 * POST /api/check-token.php
 * 
 * 供第三方网站后端调用，验证前端传来的 verify_token 是否有效
 * 
 * 请求体:
 * {
 *     "token": "verify_token_from_frontend"
 * }
 * 
 * 响应:
 * {
 *     "code": 200,
 *     "message": "令牌有效",
 *     "data": {
 *         "valid": true,
 *         "captcha_type": "slider",
 *         "verified_at": "2026-07-16 10:30:00"
 *     }
 * }
 */

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, '仅支持 POST 请求');
}

$input = getInput();
$token = $input['token'] ?? '';

if (!$token) {
    jsonResponse(400, '缺少 token 参数');
}

$db = Database::getInstance()->getConnection();

// 查找令牌
$stmt = $db->prepare("SELECT vt.*, cs.captcha_type, cs.created_at as verified_at 
    FROM verify_tokens vt 
    LEFT JOIN captcha_sessions cs ON vt.session_id = cs.session_id 
    WHERE vt.token = ? AND vt.used = 0 AND vt.expires_at > NOW()");
$stmt->execute([$token]);
$tokenRow = $stmt->fetch();

if (!$tokenRow) {
    jsonResponse(400, '令牌无效或已过期', ['valid' => false]);
}

// 标记为已使用（一次性令牌）
$db->prepare("UPDATE verify_tokens SET used = 1 WHERE id = ?")->execute([$tokenRow['id']]);

jsonResponse(200, '令牌有效', [
    'valid' => true,
    'captcha_type' => $tokenRow['captcha_type'],
    'verified_at' => $tokenRow['verified_at'],
]);