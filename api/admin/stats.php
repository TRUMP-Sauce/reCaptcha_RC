<?php
/**
 * 后台管理 API - 统计数据
 * GET /api/admin/stats.php
 */

require_once __DIR__ . '/auth_middleware.php';
adminAuth();

$db = Database::getInstance()->getConnection();

// 今日统计
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN result = 'verified' THEN 1 ELSE 0 END) as passed,
    SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) as failed
FROM verify_logs WHERE date(created_at) = ?");
$stmt->execute([$today]);
$todayStats = $stmt->fetch();

// 总统计
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN result = 'verified' THEN 1 ELSE 0 END) as passed,
    SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) as failed
FROM verify_logs");
$totalStats = $stmt->fetch();

// 各类型统计
$stmt = $db->query("SELECT 
    captcha_type,
    COUNT(*) as total,
    SUM(CASE WHEN result = 'verified' THEN 1 ELSE 0 END) as passed
FROM verify_logs WHERE DATE(created_at) = CURDATE()
GROUP BY captcha_type");
$typeStats = $stmt->fetchAll();

// 近7天趋势
$stmt = $db->query("SELECT 
    date(created_at) as day,
    COUNT(*) as total,
    SUM(CASE WHEN result = 'verified' THEN 1 ELSE 0 END) as passed
FROM verify_logs 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY date(created_at)
ORDER BY day");
$trend = $stmt->fetchAll();

// 平均响应时间
$stmt = $db->query("SELECT AVG(response_time_ms) as avg_time FROM verify_logs WHERE DATE(created_at) = CURDATE()");
$avgTime = $stmt->fetch();

$passRate = $totalStats['total'] > 0 ? round(($totalStats['passed'] / $totalStats['total']) * 100, 1) : 0;
$todayPassRate = $todayStats['total'] > 0 ? round(($todayStats['passed'] / $todayStats['total']) * 100, 1) : 0;

jsonResponse(200, '获取成功', [
    'today' => [
        'total' => (int)$todayStats['total'],
        'passed' => (int)$todayStats['passed'],
        'failed' => (int)$todayStats['failed'],
        'pass_rate' => $todayPassRate,
    ],
    'total' => [
        'total' => (int)$totalStats['total'],
        'passed' => (int)$totalStats['passed'],
        'failed' => (int)$totalStats['failed'],
        'pass_rate' => $passRate,
    ],
    'by_type' => $typeStats,
    'trend' => $trend,
    'avg_response_time_ms' => round((float)$avgTime['avg_time'], 1),
    'current_config' => [
        'captcha_type' => getSetting('captcha_type'),
        'difficulty' => getSetting('difficulty'),
        'slider_tolerance' => getSetting('slider_tolerance'),
        'click_tolerance' => getSetting('click_tolerance'),
        'session_expire' => getSetting('session_expire'),
    ]
]);