<?php
/**
 * HumanVerify PHP SDK - 第三方后端验证工具
 * 
 * 用法:
 *   require_once 'humanverify-sdk.php';
 *   
 *   // 获取前端传来的 verify_token
 *   $token = $_POST['verify_token'] ?? '';
 *   
 *   // 校验令牌
 *   $result = HumanVerifySDK::check('http://127.0.0.1', $token);
 *   
 *   if ($result['valid']) {
 *       // 验证通过，处理业务逻辑
 *   } else {
 *       // 验证失败
 *       die('验证失败: ' . $result['error']);
 *   }
 */

class HumanVerifySDK {

    /**
     * 校验 verify_token 是否有效
     * 
     * @param string $serverUrl  HumanVerify 服务器地址（如 https://captcha.example.com）
     * @param string $token      前端传来的 verify_token
     * @param int    $timeout    请求超时（秒）
     * @return array ['valid' => bool, 'error' => string, 'raw' => array]
     */
    public static function check($serverUrl, $token, $timeout = 5) {
        if (empty($token)) {
            return ['valid' => false, 'error' => '缺少验证令牌'];
        }

        $serverUrl = rtrim($serverUrl, '/');
        $url = $serverUrl . '/api/check-token.php';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['token' => $token]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['valid' => false, 'error' => '请求失败: ' . $curlError];
        }

        $data = json_decode($response, true);

        if (!$data) {
            return ['valid' => false, 'error' => '响应解析失败'];
        }

        if ($httpCode !== 200 || ($data['code'] ?? 0) !== 200) {
            return [
                'valid' => false,
                'error' => $data['message'] ?? '令牌校验失败',
                'raw'   => $data
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'raw'   => $data['data'] ?? $data
        ];
    }

    /**
     * 使用 file_get_contents 方式校验（无需 curl）
     */
    public static function checkSimple($serverUrl, $token, $timeout = 5) {
        if (empty($token)) {
            return ['valid' => false, 'error' => '缺少验证令牌'];
        }

        $serverUrl = rtrim($serverUrl, '/');
        $url = $serverUrl . '/api/check-token.php';

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\n",
                'content' => json_encode(['token' => $token]),
                'timeout' => $timeout,
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return ['valid' => false, 'error' => '请求失败'];
        }

        $data = json_decode($response, true);

        if (!$data || ($data['code'] ?? 0) !== 200) {
            return [
                'valid' => false,
                'error' => $data['message'] ?? '令牌校验失败',
                'raw'   => $data
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'raw'   => $data['data'] ?? $data
        ];
    }
}