<?php
/**
 * 管理员登录 API
 * POST /api/auth.php
 * 
 * 请求体: { "username": "admin", "password": "admin123" }
 */

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, '仅支持 POST 请求');
}

$input = getInput();
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (!$username || !$password) {
    jsonResponse(400, '用户名和密码不能为空');
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
$stmt->execute([$username]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($password, $admin['password_hash'])) {
    jsonResponse(401, '用户名或密码错误');
}

// 简单令牌
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);

// 存储令牌（简单实现）
$db->exec("CREATE TABLE IF NOT EXISTS admin_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_hash VARCHAR(255) NOT NULL,
    admin_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->prepare("INSERT INTO admin_tokens (token_hash, admin_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))")
    ->execute([$tokenHash, $admin['id']]);

jsonResponse(200, '登录成功', [
    'token' => $token,
    'username' => $admin['username'],
    'expires_in' => 86400,
]);