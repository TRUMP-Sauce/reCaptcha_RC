<?php
/**
 * HumanVerify 验证码校验 API
 * POST /api/verify.php
 * 
 * 请求体:
 * {
 *     "session_id": "xxx",
 *     "answer": "150"         // 滑块: 偏移量; 点选: "x,y"; 数学: "42"
 * }
 */

require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, '仅支持 POST 请求');
}

$input = getInput();
$sessionId = $input['session_id'] ?? '';
$userAnswer = $input['answer'] ?? '';

if (!$sessionId) {
    jsonResponse(400, '缺少 session_id', [
        'debug' => ['raw_input' => file_get_contents('php://input')]
    ]);
}

$db = Database::getInstance()->getConnection();

// 查找会话
$stmt = $db->prepare("SELECT * FROM captcha_sessions WHERE session_id = ? AND status = 'pending'");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    // 检查数据库中是否有任何会话
    $countStmt = $db->query("SELECT COUNT(*) as cnt FROM captcha_sessions");
    $totalSessions = $countStmt->fetch();
    // 查找所有 session_id 以排除类型问题
    $allStmt = $db->query("SELECT session_id, status, expires_at FROM captcha_sessions ORDER BY created_at DESC LIMIT 5");
    $recentSessions = $allStmt->fetchAll();
    jsonResponse(400, '验证会话不存在或已过期', [
        'debug' => [
            'sent_session_id' => $sessionId,
            'total_sessions' => $totalSessions['cnt'],
            'recent_sessions' => $recentSessions,
        ]
    ]);
}

// 检查过期 - 使用与 SQLite 一致的 UTC 时间
$expiresAt = strtotime($session['expires_at']);
$now = time();
if ($expiresAt === false || $expiresAt < $now) {
    $db->prepare("UPDATE captcha_sessions SET status = 'expired' WHERE id = ?")->execute([$session['id']]);
    jsonResponse(400, '验证码已过期，请刷新', [
        'debug' => [
            'expires_at' => $session['expires_at'],
            'server_time' => date('Y-m-d H:i:s', $now),
            'timezone' => date_default_timezone_get(),
        ]
    ]);
}

$startTime = $session['created_at'];

// 根据类型验证
$result = false;
$correctAnswer = $session['answer'];
$tolerance = (int)$session['tolerance'];

switch ($session['captcha_type']) {
    case 'slider':
        $userOffset = (int)$userAnswer;
        $correctOffset = (int)$correctAnswer;
        $result = abs($userOffset - $correctOffset) <= $tolerance;
        break;

    case 'click':
        $correctParts = explode(',', $correctAnswer);
        $userParts = explode(',', $userAnswer);
        if (count($userParts) === 2 && count($correctParts) === 2) {
            $dx = (int)$userParts[0] - (int)$correctParts[0];
            $dy = (int)$userParts[1] - (int)$correctParts[1];
            $distance = sqrt($dx * $dx + $dy * $dy);
            $result = $distance <= $tolerance;
        }
        break;

    case 'math':
        $result = (string)$userAnswer === (string)$correctAnswer;
        break;
}

// 计算响应时间
$responseTime = round((microtime(true) - strtotime($startTime)) * 1000);

// 更新会话状态
$newStatus = $result ? 'verified' : 'failed';
$db->prepare("UPDATE captcha_sessions SET status = ? WHERE id = ?")->execute([$newStatus, $session['id']]);

// 记录日志
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$stmt = $db->prepare("INSERT INTO verify_logs (session_id, captcha_type, user_answer, correct_answer, result, ip_address, user_agent, response_time_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$sessionId, $session['captcha_type'], $userAnswer, $correctAnswer, $newStatus, $ip, $ua, $responseTime]);

if ($result) {
    // 生成一次性验证令牌并存入数据库
    $verifyToken = generateToken();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $expiresAt = $driver === 'mysql' ? 'DATE_ADD(NOW(), INTERVAL 30 MINUTE)' : "datetime('now', '+30 minutes')";
    $stmt = $db->prepare("INSERT INTO verify_tokens (token, session_id, expires_at) VALUES (?, ?, $expiresAt)");
    $stmt->execute([$verifyToken, $sessionId]);
    jsonResponse(200, '验证通过', [
        'success' => true,
        'verify_token' => $verifyToken,
        'response_time_ms' => $responseTime,
    ]);
} else {
    jsonResponse(400, '验证失败，请重试', [
        'success' => false,
        'response_time_ms' => $responseTime,
        'debug' => [
            'user_answer' => $userAnswer,
            'correct_answer' => $correctAnswer,
            'type' => $session['captcha_type'],
        ],
    ]);
}