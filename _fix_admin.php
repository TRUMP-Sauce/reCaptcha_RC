<?php
$pdo = new PDO('mysql:host=localhost;port=3306;dbname=CDP;charset=utf8mb4', 'Admin', 'Admin@9000');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 检查现有用户
$stmt = $pdo->query("SELECT id, username FROM admins");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "当前用户: " . json_encode($users, JSON_UNESCAPED_UNICODE) . "\n";

// 更新为 Administrator
$pdo->prepare("UPDATE admins SET username = 'Administrator' WHERE username = 'Adminstrator'")->execute();
echo "已更新 username = 'Administrator'\n";

// 验证密码
$stmt = $pdo->query("SELECT id, username FROM admins");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "更新后: " . json_encode($users, JSON_UNESCAPED_UNICODE) . "\n";