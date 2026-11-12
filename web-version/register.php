<?php
/**
 * e-Barangay Web Portal - Citizen Account & Resident Registration
 * Barangay Santa Veronica District, Guimba, Nueva Ecija
 * Linked Creation: users (role='Citizen') + residents
 */

require_once __DIR__ . '/includes/db.php';
$pdo = getDb();
$profile = getBarangayProfile($pdo);

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$formData = [
    'username' => '',
    'email' => '',
    'contact_number' => '',
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'suffix' => '',
    'birth_date' => '',
    'sex' => 'Male',
    'civil_status' => 'Single',
    'citizenship' => 'Filipino',
    'purok_zone' => 'Purok 1',
    'street_address' => '',
    'household_number' => '',
    'residency_start_date' => date('Y-m-d', strtotime('-1 year')),
    'is_pwd' => 0,
    'is_solo_parent' => 0
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Populate form data
    foreach ($formData as $key => $val) {
        if (isset($_POST[$key])) {
            $formData[$key] = is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
        }
    }
    $formData['is_pwd'] = isset($_POST['is_pwd']) ? 1 : 0;
    $formData['is_solo_parent'] = isset($_POST['is_solo_parent']) ? 1 : 0;

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($formData['username'])) {
        $errors[] = 'Username is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $formData['username'])) {
        $errors[] = 'Username must be 3-30 characters and contain only letters, numbers, and underscores.';
    }

    if (empty($formData['first_name']) || empty($formData['last_name'])) {
        $errors[] = 'First Name and Last Name are required.';
    }

    if (empty($formData['birth_date'])) {
        $errors[] = 'Birth date is required.';
    }

    if (empty($formData['street_address'])) {
        $errors[] = 'Street address is required.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    // Check unique username
    if (empty($errors)) {
        $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE username = :u LIMIT 1");
        $checkStmt->execute([':u' => $formData['username']]);
        if ($checkStmt->fetch()) {
            $errors[] = 'Username is already taken. Please choose another.';
        }
    }

    // Check unique email if provided
    if (empty($errors) && !empty($formData['email'])) {
        if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $checkEmail = $pdo->prepare("SELECT user_id FROM users WHERE email = :e LIMIT 1");
            $checkEmail->execute([':e' => $formData['email']]);
            if ($checkEmail->fetch()) {
                $errors[] = 'Email address is already in use by another account.';
            }
        }
    }

    // Process Registration in DB Transaction
    if (empty($errors)) {
        $pdo->beginTransaction();
        try {
            // Full name construction
            $fullName = trim($formData['first_name'] . ' ' . ($formData['middle_name'] ? $formData['middle_name'] . ' ' : '') . $formData['last_name'] . ($formData['suffix'] ? ' ' . $formData['suffix'] : ''));

            // Rule 3: Use SHA-256 password hash matching existing users table
            $passwordHash = hashPassword($password);

            // 1. Insert into users (role = 'Citizen')
            $userInsert = $pdo->prepare("
                INSERT INTO users (
                    username, full_name, email, contact_number, password_hash,
                    role, verification_status, mfa_enabled, is_active, created_at
                ) VALUES (
                    :username, :full_name, :email, :contact_number, :password_hash,
                    'Citizen', 'Verified', 0, 1, NOW()
                )
            ");
            $userInsert->execute([
                ':username' => $formData['username'],
                ':full_name' => $fullName,
                ':email' => !empty($formData['email']) ? $formData['email'] : null,
                ':contact_number' => !empty($formData['contact_number']) ? $formData['contact_number'] : null,
                ':password_hash' => $passwordHash
            ]);
            $newUserId = (int)$pdo->lastInsertId();

            // 2. Determine Senior Citizen status (Age >= 60)
            $birthDateObj = new DateTime($formData['birth_date']);
            $today = new DateTime();
            $age = $today->diff($birthDateObj)->y;
            $isSeniorCitizen = ($age >= 60) ? 1 : 0;

            // 3. Generate resident_code: RES-YYYY-XXXX (Consistent with C# WinForms)
            $seqStmt = $pdo->query("SELECT COALESCE(MAX(resident_id), 0) + 1 FROM residents");
            $nextResidentNum = (int)$seqStmt->fetchColumn();
            $residentCode = sprintf("RES-%s-%04d", date('Y'), $nextResidentNum);

            // 4. Insert into residents (linked to new user_id)
            $resInsert = $pdo->prepare("
                INSERT INTO residents (
                    user_id, resident_code, first_name, middle_name, last_name, suffix,
                    birth_date, sex, civil_status, citizenship, purok_zone, street_address,
                    household_number, residency_start_date, contact_number,
                    is_senior_citizen, is_pwd, is_solo_parent, has_pending_dispute, is_verified, created_at
                ) VALUES (
                    :user_id, :resident_code, :first_name, :middle_name, :last_name, :suffix,
                    :birth_date, :sex, :civil_status, :citizenship, :purok_zone, :street_address,
                    :household_number, :residency_start_date, :contact_number,
                    :is_senior_citizen, :is_pwd, :is_solo_parent, 0, 1, NOW()
                )
            ");
            $resInsert->execute([
                ':user_id' => $newUserId,
                ':resident_code' => $residentCode,
                ':first_name' => $formData['first_name'],
                ':middle_name' => !empty($formData['middle_name']) ? $formData['middle_name'] : null,
                ':last_name' => $formData['last_name'],
                ':suffix' => !empty($formData['suffix']) ? $formData['suffix'] : null,
                ':birth_date' => $formData['birth_date'],
                ':sex' => $formData['sex'],
                ':civil_status' => $formData['civil_status'],
                ':citizenship' => !empty($formData['citizenship']) ? $formData['citizenship'] : 'Filipino',
                ':purok_zone' => $formData['purok_zone'],
                ':street_address' => $formData['street_address'],
                ':household_number' => !empty($formData['household_number']) ? $formData['household_number'] : null,
                ':residency_start_date' => $formData['residency_start_date'],
                ':contact_number' => !empty($formData['contact_number']) ? $formData['contact_number'] : null,
                ':is_senior_citizen' => $isSeniorCitizen,
                ':is_pwd' => $formData['is_pwd'],
                ':is_solo_parent' => $formData['is_solo_parent']
            ]);
            $newResidentId = (int)$pdo->lastInsertId();

            // Strict Rule 2: Audit log with ip_or_device = 'WEB-PORTAL'
            logAudit(
                $pdo,
                $newUserId,
                'CITIZEN_REGISTER',
                'users',
                $newUserId,
                null,
                [
                    'username' => $formData['username'],
                    'resident_code' => $residentCode,
                    'resident_id' => $newResidentId,
                    'full_name' => $fullName
                ]
            );

            $pdo->commit();

            // Auto-login citizen into session
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $formData['username'];
            $_SESSION['full_name'] = $fullName;
            $_SESSION['role'] = 'Citizen';
            $_SESSION['email'] = $formData['email'];
            $_SESSION['resident_id'] = $newResidentId;
            $_SESSION['resident_code'] = $residentCode;
            $_SESSION['user'] = [
                'user_id' => $newUserId,
                'username' => $formData['username'],
                'full_name' => $fullName,
                'email' => $formData['email'],
                'role' => 'Citizen'
            ];
            $_SESSION['resident'] = [
                'resident_id' => $newResidentId,
                'resident_code' => $residentCode,
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'purok_zone' => $formData['purok_zone'],
                'street_address' => $formData['street_address'],
                'is_senior_citizen' => $isSeniorCitizen,
                'is_pwd' => $formData['is_pwd'],
                'is_solo_parent' => $formData['is_solo_parent']
            ];

            setFlash('success', 'Registration successful! Your Resident Code is <strong>' . htmlspecialchars($residentCode) . '</strong>.');
            header('Location: dashboard.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Registration failed: ' . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
    }
}

$pageTitle = "Citizen Registration";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white py-4 px-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="navbar-brand-seal" style="width: 52px; height: 52px; font-size: 1.5rem;">
                            <i class="bi bi-person-vcard-fill text-primary"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0">Citizen Account Registration</h4>
                            <p class="small text-info mb-0">Barangay Santa Veronica District, Guimba, Nueva Ecija</p>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" role="alert">
                            <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>Please correct the following:</h6>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="register.php" method="POST" novalidate>
                        <!-- Section 1: Account Login Credentials -->
                        <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                            <i class="bi bi-shield-lock me-1"></i> 1. Online Account Credentials
                        </h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="username" class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" value="<?= e($formData['username']) ?>" placeholder="e.g. maria_santos" required>
                                <small class="text-muted">Unique login handle (letters, numbers, underscores)</small>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">Email Address (Optional)</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= e($formData['email']) ?>" placeholder="maria@example.com">
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Min. 6 characters" required>
                                    <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="password" tabindex="-1">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                                    <button class="btn btn-outline-secondary btn-toggle-password" type="button" data-target="confirm_password" tabindex="-1">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Personal Identification -->
                        <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                            <i class="bi bi-person-badge me-1"></i> 2. Resident Identification
                        </h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?= e($formData['first_name']) ?>" placeholder="First name" required>
                            </div>
                            <div class="col-md-3">
                                <label for="middle_name" class="form-label fw-semibold">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?= e($formData['middle_name']) ?>" placeholder="Middle name">
                            </div>
                            <div class="col-md-3">
                                <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?= e($formData['last_name']) ?>" placeholder="Last name" required>
                            </div>
                            <div class="col-md-2">
                                <label for="suffix" class="form-label fw-semibold">Suffix</label>
                                <input type="text" class="form-control" id="suffix" name="suffix" value="<?= e($formData['suffix']) ?>" placeholder="Jr., III">
                            </div>

                            <div class="col-md-4">
                                <label for="birth_date" class="form-label fw-semibold">Birth Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?= e($formData['birth_date']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="sex" class="form-label fw-semibold">Sex <span class="text-danger">*</span></label>
                                <select class="form-select" id="sex" name="sex">
                                    <option value="Male" <?= $formData['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $formData['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= $formData['sex'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="civil_status" class="form-label fw-semibold">Civil Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="civil_status" name="civil_status">
                                    <option value="Single" <?= $formData['civil_status'] === 'Single' ? 'selected' : '' ?>>Single</option>
                                    <option value="Married" <?= $formData['civil_status'] === 'Married' ? 'selected' : '' ?>>Married</option>
                                    <option value="Widowed" <?= $formData['civil_status'] === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                                    <option value="Separated" <?= $formData['civil_status'] === 'Separated' ? 'selected' : '' ?>>Separated</option>
                                    <option value="Live-In" <?= $formData['civil_status'] === 'Live-In' ? 'selected' : '' ?>>Live-In</option>
                                </select>
                            </div>
                        </div>

                        <!-- Section 3: Residency & Address in Santa Veronica District -->
                        <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                            <i class="bi bi-geo-alt me-1"></i> 3. Address & Santa Veronica Residency
                        </h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="purok_zone" class="form-label fw-semibold">Purok / Zone <span class="text-danger">*</span></label>
                                <select class="form-select" id="purok_zone" name="purok_zone">
                                    <option value="Purok 1" <?= $formData['purok_zone'] === 'Purok 1' ? 'selected' : '' ?>>Purok 1</option>
                                    <option value="Purok 2" <?= $formData['purok_zone'] === 'Purok 2' ? 'selected' : '' ?>>Purok 2</option>
                                    <option value="Purok 3" <?= $formData['purok_zone'] === 'Purok 3' ? 'selected' : '' ?>>Purok 3</option>
                                    <option value="Purok 4" <?= $formData['purok_zone'] === 'Purok 4' ? 'selected' : '' ?>>Purok 4</option>
                                    <option value="Purok 5" <?= $formData['purok_zone'] === 'Purok 5' ? 'selected' : '' ?>>Purok 5</option>
                                    <option value="Purok 6" <?= $formData['purok_zone'] === 'Purok 6' ? 'selected' : '' ?>>Purok 6</option>
                                    <option value="Purok 7" <?= $formData['purok_zone'] === 'Purok 7' ? 'selected' : '' ?>>Purok 7</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label for="street_address" class="form-label fw-semibold">House / Street Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="street_address" name="street_address" value="<?= e($formData['street_address']) ?>" placeholder="House No., Street Name, Santa Veronica District, Guimba" required>
                            </div>
                            <div class="col-md-4">
                                <label for="household_number" class="form-label fw-semibold">Household No. (Optional)</label>
                                <input type="text" class="form-control" id="household_number" name="household_number" value="<?= e($formData['household_number']) ?>" placeholder="e.g. HH-0105">
                            </div>
                            <div class="col-md-4">
                                <label for="residency_start_date" class="form-label fw-semibold">Residency Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="residency_start_date" name="residency_start_date" value="<?= e($formData['residency_start_date']) ?>" required>
                                <small class="text-muted">Used for RA 11261 (>= 6 mos qualification)</small>
                            </div>
                            <div class="col-md-4">
                                <label for="contact_number" class="form-label fw-semibold">Contact Mobile No.</label>
                                <input type="tel" class="form-control" id="contact_number" name="contact_number" value="<?= e($formData['contact_number']) ?>" placeholder="09181234567">
                            </div>
                        </div>

                        <!-- Section 4: Special Classifications / Priority Level -->
                        <h5 class="fw-bold text-primary border-bottom pb-2 mb-3">
                            <i class="bi bi-heart-pulse me-1"></i> 4. Priority / Special Classification
                        </h5>
                        <p class="small text-muted mb-3">
                            Eligible residents receive priority queue ticketing (P-XXX) at the Barangay Hall. Senior Citizen status is automatically determined from your birth date.
                        </p>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="form-check p-3 bg-light rounded border">
                                    <input class="form-check-input" type="checkbox" id="is_pwd" name="is_pwd" value="1" <?= $formData['is_pwd'] ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="is_pwd">
                                        <i class="bi bi-person-wheelchair text-primary me-1"></i> Person with Disability (PWD)
                                    </label>
                                    <div class="small text-muted mt-1">Entitled to express queue priority (Rank 5).</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check p-3 bg-light rounded border">
                                    <input class="form-check-input" type="checkbox" id="is_solo_parent" name="is_solo_parent" value="1" <?= $formData['is_solo_parent'] ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="is_solo_parent">
                                        <i class="bi bi-people text-primary me-1"></i> Solo Parent
                                    </label>
                                    <div class="small text-muted mt-1">Eligible for free Solo Parent Certification (CERT-SOL).</div>
                                </div>
                            </div>
                        </div>

                        <!-- Submission Button -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg shadow">
                                <i class="bi bi-person-check-fill me-1"></i> Complete Registration & Open Portal
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <span class="text-muted small">Already registered? </span>
                        <a href="login.php" class="fw-semibold">Sign in here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
