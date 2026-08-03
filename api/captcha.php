<?php
/**
 * HumanVerify 验证码生成 API
 * GET/POST /api/captcha.php
 * 
 * 请求参数: ?type=slider|click|math
 * 返回: 验证码会话数据
 */

require_once __DIR__ . '/../config/config.php';

cleanExpiredSessions();

$type = $_GET['type'] ?? $_POST['type'] ?? getSetting('captcha_type') ?? 'slider';
$difficulty = $_GET['difficulty'] ?? $_POST['difficulty'] ?? getSetting('difficulty') ?? 'normal';
$sessionExpire = (int)(getSetting('session_expire') ?? 300);

$db = Database::getInstance()->getConnection();
$sessionId = generateSessionId();

switch ($type) {
    case 'slider':
        $data = generateSliderCaptcha($db, $sessionId, $difficulty, $sessionExpire);
        break;
    case 'click':
        $data = generateClickCaptcha($db, $sessionId, $difficulty, $sessionExpire);
        break;
    case 'math':
        $data = generateMathCaptcha($db, $sessionId, $difficulty, $sessionExpire);
        break;
    default:
        jsonResponse(400, '不支持的验证类型: ' . $type);
}

jsonResponse(200, '验证码生成成功', $data);

/**
 * 滑块验证码
 */
function generateSliderCaptcha($db, $sessionId, $difficulty, $expire) {
    // 滑块宽度
    $sliderWidth = 50;
    $totalWidth = 300;
    $maxOffset = $totalWidth - $sliderWidth;

    // 根据难度调整正确答案范围
    switch ($difficulty) {
        case 'easy':
            $answer = rand(40, 120);
            $tolerance = 8;
            break;
        case 'hard':
            $answer = rand(80, 220);
            $tolerance = 3;
            break;
        default: // normal
            $answer = rand(50, 200);
            $tolerance = 5;
    }

    // 存储到数据库
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $expiresAt = $driver === 'mysql' ? 'DATE_ADD(NOW(), INTERVAL ? SECOND)' : "datetime('now', '+' || ? || ' seconds')";
    $stmt = $db->prepare("INSERT INTO captcha_sessions (session_id, captcha_type, answer, tolerance, expires_at) VALUES (?, ?, ?, ?, $expiresAt)");
    $stmt->execute([$sessionId, 'slider', (string)$answer, $tolerance, $expire]);

    return [
        'session_id' => $sessionId,
        'type' => 'slider',
        'slider_width' => $sliderWidth,
        'total_width' => $totalWidth,
        'max_offset' => $maxOffset,
        'answer_pos' => (int)$answer,  // 缺口位置，用于前端渲染
        'difficulty' => $difficulty,
        'expires_in' => $expire,
        'bg_seed' => rand(1000, 9999),
        'puzzle_seed' => rand(1000, 9999),
    ];
}

/**
 * 点选验证码
 */
function generateClickCaptcha($db, $sessionId, $difficulty, $expire) {
    $width = 300;
    $height = 200;

    switch ($difficulty) {
        case 'easy':
            $tolerance = 40;
            break;
        case 'hard':
            $tolerance = 20;
            break;
        default:
            $tolerance = 30;
    }

    $targetX = rand(60, $width - 60);
    $targetY = rand(60, $height - 60);

    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $expiresAt = $driver === 'mysql' ? 'DATE_ADD(NOW(), INTERVAL ? SECOND)' : "datetime('now', '+' || ? || ' seconds')";
    $stmt = $db->prepare("INSERT INTO captcha_sessions (session_id, captcha_type, answer, tolerance, expires_at) VALUES (?, ?, ?, ?, $expiresAt)");
    $stmt->execute([$sessionId, 'click', "$targetX,$targetY", $tolerance, $expire]);

    return [
        'session_id' => $sessionId,
        'type' => 'click',
        'width' => $width,
        'height' => $height,
        'tolerance' => $tolerance,
        'target_x' => $targetX,
        'target_y' => $targetY,
        'difficulty' => $difficulty,
        'expires_in' => $expire,
        'bg_seed' => rand(1000, 9999),
    ];
}

/**
 * 数学题验证码
 */
function generateMathCaptcha($db, $sessionId, $difficulty, $expire) {
    switch ($difficulty) {
        case 'easy':
            $a = rand(1, 20);
            $b = rand(1, 20);
            $ops = ['+', '-'];
            break;
        case 'hard':
            $a = rand(10, 99);
            $b = rand(10, 99);
            $ops = ['+', '-', '*'];
            break;
        default:
            $a = rand(1, 50);
            $b = rand(1, 50);
            $ops = ['+', '-', '*'];
    }

    $op = $ops[array_rand($ops)];

    switch ($op) {
        case '+': $answer = $a + $b; break;
        case '-': $answer = $a - $b; break;
        case '*': $answer = $a * $b; break;
    }

    $question = "$a $op $b = ?";

    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $expiresAt = $driver === 'mysql' ? 'DATE_ADD(NOW(), INTERVAL ? SECOND)' : "datetime('now', '+' || ? || ' seconds')";
    $stmt = $db->prepare("INSERT INTO captcha_sessions (session_id, captcha_type, answer, tolerance, expires_at) VALUES (?, ?, ?, ?, $expiresAt)");
    $stmt->execute([$sessionId, 'math', (string)$answer, 0, $expire]);

    return [
        'session_id' => $sessionId,
        'type' => 'math',
        'question' => $question,
        'difficulty' => $difficulty,
        'expires_in' => $expire,
    ];
}