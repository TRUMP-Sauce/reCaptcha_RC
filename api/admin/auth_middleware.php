<?php
/**
 * 后台管理 API - 认证中间件
 */

require_once __DIR__ . '/../../config/config.php';

function adminAuth() {
    $token = getBearerToken();
    if (!$token) {
        jsonResponse(401, '未提供管理令牌');
    }

    $tokenHash = hash('sha256', $token);
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM admin_tokens WHERE token_hash = ? AND expires_at > NOW()");
    $stmt->execute([$tokenHash]);
    $tokenRow = $stmt->fetch();

    if (!$tokenRow) {
        jsonResponse(401, '令牌无效或已过期');
    }

    return $tokenRow;
}