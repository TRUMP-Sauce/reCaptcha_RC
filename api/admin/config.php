<?php
/**
 * 后台管理 API - 配置管理
 * GET  /api/admin/config.php  - 获取配置
 * POST /api/admin/config.php  - 更新配置
 */

require_once __DIR__ . '/auth_middleware.php';
adminAuth();

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query("SELECT `key`, value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['key']] = $row['value'];
    }
    jsonResponse(200, '获取成功', ['settings' => $settings]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getInput();
    $allowed = ['slider_tolerance', 'click_tolerance', 'session_expire', 'max_attempts', 'captcha_type', 'difficulty'];

    $updated = [];
    $stmt = $db->prepare("UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP WHERE `key` = ?");
    foreach ($input as $key => $value) {
        if (in_array($key, $allowed)) {
            $stmt->execute([(string)$value, $key]);
            $updated[] = $key;
        }
    }

    if (empty($updated)) {
        jsonResponse(400, '没有可更新的配置项');
    }

    jsonResponse(200, '配置更新成功', ['updated' => $updated]);
}

jsonResponse(405, '仅支持 GET 和 POST 请求');