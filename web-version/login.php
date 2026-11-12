<?php
/**
 * e-Barangay Web Portal - Citizen Login
 * Barangay Santa Veronica District, Guimba, Nueva Ecija
 */

require_once __DIR__ . '/includes/db.php';
$pdo = getDb();
$profile = getBarangayProfile($pdo);

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$usernameVal = '';
$redirectServiceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $usernameVal = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirectServiceId = !empty($_POST['redirect_service_id']) ? (int)$_POST['redirect_service_id'] : null;

    if (empty($usernameVal) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT user_id, username, full_name, email, contact_number, password_hash, role, verification_status, is_active
                FROM users
                WHERE (username = :u1 OR email = :u2)
                LIMIT 1
            ");
            $stmt->execute([':u1' => $usernameVal, ':u2' => $usernameVal]);
            $user = $stmt->fetch();

            if (!$user || !verifyPassword($password, $user['password_hash'])) {
                $error = 'Invalid username or password.';
            } elseif (!$user['is_active']) {
                $error = 'This account has been deactivated. Please contact the Barangay Hall.';
            } elseif ($user['verification_status'] === 'Suspended') {
                $error = 'This account is currently suspended. Please contact the Barangay Secretary.';
            } else {
                // Successful authentication
                // Update last_login_at
                $upd = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE user_id = :uid");
                $upd->execute([':uid' => $user['user_id']]);

                // Find linked resident record
                $resStmt = $pdo->prepare("
                    SELECT resident_id, resident_code, first_name, last_name, purok_zone, street_address,
                           is_senior_citizen, is_pwd, is_solo_parent, is_verified
                    FROM residents
                    WHERE user_id = :uid
                    LIMIT 1
                ");
                $resStmt->execute([':uid' => $user['user_id']]);
                $resident = $resStmt->fetch();

                // Store in session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user'] = $user;

                if ($resident) {
                    $_SESSION['resident_id'] = $resident['resident_id'];
                    $_SESSION['resident_code'] = $resident['resident_code'];
                    $_SESSION['resident'] = $resident;
                } else {
                    $_SESSION['resident_id'] = null;
                    $_SESSION['resident_code'] = 'NEW-CITIZEN';
                    $_SESSION['resident'] = null;
                }

                // Strict Rule 2: Audit log with ip_or_device = 'WEB-PORTAL'
                logAudit(
                    $pdo,
                    $user['user_id'],
                    'USER_LOGIN',
                    'users',
                    $user['user_id'],
                    null,
                    ['username' => $user['username'], 'role' => $user['role']]
                );

                setFlash('success', 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!');
                
                if ($redirectServiceId) {
                    header('Location: dashboard.php?service_id=' . $redirectServiceId . '#apply');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            }
        } catch (Exception $e) {
            $error = 'An unexpected login error occurred. Please try again.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

$pageTitle = "Citizen Sign In";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white text-center py-4">
                    <div class="navbar-brand-seal mx-auto mb-2" style="width: 54px; height: 54px; font-size: 1.6rem;">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Citizen Portal Sign In</h4>
                    <p class="small text-info mb-0"><?= e($profile['barangay_name']) ?></p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="login.php" method="POST" novalidate>
                        <?php if ($redirectServiceId): ?>
                            <input type="hidden" name="redirect_service_id" value="<?= $redirectServiceId ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">Username or Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="username" name="username" value="<?= e($usernameVal) ?>" placeholder="e.g. juan_delacruz" required autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                                <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="password" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right"></i> Sign In to Portal
                            </button>
                        </div>
                    </form>

                    <!-- Demo Credentials Helper Callout -->
                    <div class="mt-4 p-3 bg-light rounded-3 border small">
                        <div class="d-flex align-items-center gap-2 text-primary fw-bold mb-2">
                            <i class="bi bi-lightbulb-fill text-warning"></i> Demo Testing Credentials:
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span>Username: <code>juan_delacruz</code></span>
                            <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" onclick="document.getElementById('username').value='juan_delacruz'; document.getElementById('password').value='DemoPass123!';">Autofill</button>
                        </div>
                        <div>Password: <code>DemoPass123!</code></div>
                        <small class="text-muted d-block mt-1">Shares password hash with local C# WinForms application.</small>
                    </div>

                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="text-muted small mb-1">Don't have an online citizen account?</p>
                        <a href="register.php" class="btn btn-outline-dark btn-sm fw-semibold">
                            <i class="bi bi-person-plus-fill me-1"></i> Register as a Resident
                        </a>
                    </div>
                </div>
            </div>

            <div class="text-center mt-3">
                <a href="index.php" class="text-muted small"><i class="bi bi-arrow-left me-1"></i>Return to Homepage</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
