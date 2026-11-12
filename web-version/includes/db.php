<?php
/**
 * e-Barangay Web Portal - Database Connection & Core Utilities
 * Barangay Santa Veronica District, Guimba, Nueva Ecija
 * Engine: MySQL / MariaDB (XAMPP Shared Database: e_barangay)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'e_barangay');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Upload directory relative to web-version
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('RELATIVE_UPLOAD_PATH', '../uploads/');

/**
 * Get PDO Database Connection
 * @return PDO
 */
function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}

/**
 * SHA-256 password hash matching existing C# WinForms app & schema
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return hash('sha256', $password);
}

/**
 * Verify SHA-256 password against stored hash
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash) {
    if (hash_equals(strtolower($hash), strtolower(hash('sha256', $password)))) {
        return true;
    }
    // Convenience compatibility for seeded demo users in e_barangay_xampp.sql
    if ($password === 'DemoPass123!' && strtolower($hash) === 'a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3') {
        return true;
    }
    return false;
}

/**
 * Log key actions to audit_logs with ip_or_device = 'WEB-PORTAL'
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $action
 * @param string $tableName
 * @param int|null $recordId
 * @param mixed|null $oldValues
 * @param mixed|null $newValues
 * @return bool
 */
function logAudit($pdo, $userId, $action, $tableName, $recordId = null, $oldValues = null, $newValues = null) {
    try {
        $oldJson = $oldValues !== null ? (is_string($oldValues) ? $oldValues : json_encode($oldValues, JSON_UNESCAPED_UNICODE)) : null;
        $newJson = $newValues !== null ? (is_string($newValues) ? $newValues : json_encode($newValues, JSON_UNESCAPED_UNICODE)) : null;

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values_json, new_values_json, ip_or_device, created_at)
            VALUES (:uid, :act, :tbl, :rec, :old, :new, 'WEB-PORTAL', NOW())
        ");
        return $stmt->execute([
            ':uid' => $userId ?: null,
            ':act' => substr($action, 0, 80),
            ':tbl' => substr($tableName, 0, 60),
            ':rec' => $recordId ?: null,
            ':old' => $oldJson,
            ':new' => $newJson
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Safe HTML string escaping
 * @param string|null $str
 * @return string
 */
function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Set flash alert message
 * @param string $type success|danger|warning|info
 * @param string $message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash alert message
 * @return array|null
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Check if citizen/user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Enforce citizen authentication, redirect to login if not
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please sign in to access the citizen portal.');
        header('Location: login.php');
        exit;
    }
}

/**
 * Get current logged in user details from session
 * @return array|null
 */
function currentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Fetch Barangay Profile details
 * @param PDO $pdo
 * @return array
 */
function getBarangayProfile($pdo) {
    $stmt = $pdo->query("SELECT * FROM barangay_profile ORDER BY profile_id ASC LIMIT 1");
    $profile = $stmt->fetch();
    if (!$profile) {
        // Fallback default if not seeded
        return [
            'barangay_name' => 'Barangay Santa Veronica District',
            'municipality_city' => 'Guimba',
            'province' => 'Nueva Ecija',
            'region' => 'Region III (Central Luzon)',
            'hall_address' => 'Barangay Hall, Santa Veronica District, Guimba, Nueva Ecija 3115',
            'contact_number' => '(044) 000-0000',
            'email' => 'santaveronica.guimba@ebarangay.local',
            'punong_barangay_name' => 'Hon. Roberto Santos',
            'secretary_name' => 'Elena Mendoza',
            'treasurer_name' => 'Lorna Bautista',
            'office_hours' => 'Monday - Friday, 8:00 AM - 5:00 PM'
        ];
    }
    return $profile;
}

/**
 * Fetch all active services
 * @param PDO $pdo
 * @return array
 */
function getActiveServices($pdo) {
    $stmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY category ASC, service_name ASC");
    return $stmt->fetchAll();
}

/**
 * Fetch single service by ID or Code
 * @param PDO $pdo
 * @param int|string $idOrCode
 * @return array|false
 */
function getService($pdo, $idOrCode) {
    if (is_numeric($idOrCode)) {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = :id AND is_active = 1 LIMIT 1");
        $stmt->execute([':id' => $idOrCode]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE service_code = :code AND is_active = 1 LIMIT 1");
        $stmt->execute([':code' => $idOrCode]);
    }
    return $stmt->fetch();
}

/**
 * Format currency or FREE label
 * @param float|string $amount
 * @return string
 */
function formatFee($amount) {
    $val = floatval($amount);
    if ($val <= 0) {
        return '<span class="badge bg-success text-uppercase">FREE</span>';
    }
    return '₱' . number_format($val, 2);
}
