<?php
/**
 * 后台管理 API - 验证日志
 * GET /api/admin/logs.php?page=1&limit=20&type=slider&result=verified
 */

require_once __DIR__ . '/auth_middleware.php';
adminAuth();

$db = Database::getInstance()->getConnection();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$type = $_GET['type'] ?? '';
$result = $_GET['result'] ?? '';

$where = [];
$params = [];
if ($type) {
    $where[] = "captcha_type = ?";
    $params[] = $type;
}
if ($result) {
    $where[] = "result = ?";
    $params[] = $result;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// 总数
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM verify_logs $whereClause");
$countStmt->execute($params);
$total = (int)$countStmt->fetch()['total'];

// 查询列表
$stmt = $db->prepare("SELECT * FROM verify_logs $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$limit, $offset]);
$stmt->execute($allParams);
$logs = $stmt->fetchAll();

jsonResponse(200, '获取成功', [
    'logs' => $logs,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => ceil($total / $limit),
    ]
]);