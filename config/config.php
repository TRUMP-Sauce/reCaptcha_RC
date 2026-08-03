<?php
/**
 * HumanVerify - 通用配置
 */

date_default_timezone_set('UTC');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/database.php';

function jsonResponse($code, $message, $data = null) {
    http_response_code($code);
    $res = ['code' => $code, 'message' => $message, 'timestamp' => time()];
    if ($data !== null) $res['data'] = $data;
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

function getInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return $data ?: [];
}

function getSetting($key) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : null;
}

function getBearerToken() {
    $headers = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if ($headers && preg_match('/Bearer\s+(.*)$/i', $headers, $m)) {
        return $m[1];
    }
    return null;
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function generateSessionId() {
    return bin2hex(random_bytes(16));
}

/**
 * 获取数据库适配的 datetime 表达式
 */
function dbDateAdd($db, $interval, $unit = 'SECOND') {
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        return "DATE_ADD(NOW(), INTERVAL $interval $unit)";
    }
    return "datetime('now', '+' || $interval || ' seconds')";
}

// 清理过期会话
function cleanExpiredSessions() {
    $db = Database::getInstance()->getConnection();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $now = $driver === 'mysql' ? 'NOW()' : "datetime('now')";
    $db->exec("DELETE FROM captcha_sessions WHERE expires_at < $now");
}