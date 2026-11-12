<?php
/**
 * e-Barangay Web Portal - Logout Handler
 * Barangay Santa Veronica District, Guimba, Nueva Ecija
 */

require_once __DIR__ . '/includes/db.php';
$pdo = getDb();

if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'User';

    // Strict Rule 2: Audit log with ip_or_device = 'WEB-PORTAL'
    logAudit(
        $pdo,
        $userId,
        'USER_LOGOUT',
        'users',
        $userId,
        null,
        ['username' => $username, 'action' => 'logout']
    );
}

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start fresh session to set flash notice
session_start();
setFlash('info', 'You have been safely signed out. Thank you for using the Santa Veronica e-Portal.');
header('Location: login.php');
exit;
