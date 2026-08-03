<?php
/**
 * HumanVerify - 数据库配置
 * 支持 MySQL 和 SQLite
 */

class Database {
    private static $instance = null;
    private $db;

    private function __construct() {
        $dbType = 'mysql'; // mysql 或 sqlite

        if ($dbType === 'mysql') {
            $host = 'localhost';
            $port = '3306';
            $dbname = 'CDP';
            $user = 'Admin';
            $pass = 'Admin@9000';

            $this->db = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } else {
            $dbPath = __DIR__ . '/../data/humanverify.db';
            $dataDir = dirname($dbPath);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            $this->db = new PDO("sqlite:$dbPath");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        $this->initTables();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->db;
    }

    private function initTables() {
        // 判断数据库类型
        $isMysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';

        if ($isMysql) {
            $this->initTablesMysql();
        } else {
            $this->initTablesSqlite();
        }
    }

    private function initTablesMysql() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS captcha_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) UNIQUE NOT NULL,
            captcha_type VARCHAR(20) NOT NULL,
            answer TEXT NOT NULL,
            tolerance INT DEFAULT 5,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS verify_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64),
            captcha_type VARCHAR(20),
            user_answer TEXT,
            correct_answer TEXT,
            result VARCHAR(20),
            ip_address VARCHAR(45),
            user_agent TEXT,
            response_time_ms INT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS settings (
            `key` VARCHAR(64) PRIMARY KEY,
            `value` TEXT NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS verify_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(128) UNIQUE NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            used INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->exec("CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(64) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->initDefaultSettings();
        $this->initDefaultAdmin();
    }

    private function initTablesSqlite() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS captcha_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id TEXT UNIQUE NOT NULL,
            captcha_type TEXT NOT NULL,
            answer TEXT NOT NULL,
            tolerance INTEGER DEFAULT 5,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS verify_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id TEXT,
            captcha_type TEXT,
            user_answer TEXT,
            correct_answer TEXT,
            result TEXT,
            ip_address TEXT,
            user_agent TEXT,
            response_time_ms INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS verify_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token TEXT UNIQUE NOT NULL,
            session_id TEXT NOT NULL,
            used INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->initDefaultSettings();
        $this->initDefaultAdmin();
    }

    private function initDefaultSettings() {
        $isMysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $defaults = [
            'slider_tolerance' => '5',
            'click_tolerance' => '30',
            'session_expire' => '300',
            'max_attempts' => '3',
            'captcha_type' => 'slider',
            'difficulty' => 'normal',
        ];

        if ($isMysql) {
            $stmt = $this->db->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)");
        } else {
            $stmt = $this->db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
        }

        foreach ($defaults as $k => $v) {
            $stmt->execute([$k, $v]);
        }
    }

    private function initDefaultAdmin() {
        $isMysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $stmt = $this->db->prepare("SELECT id FROM admins WHERE username = 'Administrator'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $hash = password_hash('Administrator@9000', PASSWORD_BCRYPT, ['cost' => 12]);
            if ($isMysql) {
                $this->db->prepare("INSERT IGNORE INTO admins (username, password_hash) VALUES (?, ?)")
                    ->execute(['Administrator', $hash]);
            } else {
                $this->db->prepare("INSERT OR IGNORE INTO admins (username, password_hash) VALUES (?, ?)")
                    ->execute(['Administrator', $hash]);
            }
        }
    }
}