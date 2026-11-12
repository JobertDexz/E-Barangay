<?php
/**
 * e-Barangay Web Portal - Citizen Self-Service Dashboard
 * Barangay Santa Veronica District, Guimba, Nueva Ecija
 *
 * Capabilities:
 * 1. Apply Online (intake_channel = 'Online Portal')
 * 2. Upload Requirements (saves to ../uploads/, stores path + SHA-256 checksum)
 * 3. Enforce RA 11261 fields for First-Time Jobseeker
 * 4. Track Application Status & History
 * 5. View Live Queue Tickets ("Now Serving" + Ticket Position)
 * 6. Audit Logging (ip_or_device = 'WEB-PORTAL')
 */

require_once __DIR__ . '/includes/db.php';
$pdo = getDb();
requireLogin();

$currentUser = currentUser();
$userId = (int)$_SESSION['user_id'];

// Fetch or refresh linked resident details
$resStmt = $pdo->prepare("
    SELECT resident_id, resident_code, first_name, middle_name, last_name, suffix,
           birth_date, sex, civil_status, citizenship, purok_zone, street_address,
           household_number, residency_start_date, contact_number,
           is_senior_citizen, is_pwd, is_solo_parent, has_pending_dispute, is_verified
    FROM residents
    WHERE user_id = :uid
    LIMIT 1
");
$resStmt->execute([':uid' => $userId]);
$resident = $resStmt->fetch();

if (!$resident) {
    // If citizen account has no linked resident yet, create one from users table data
    $seqStmt = $pdo->query("SELECT COALESCE(MAX(resident_id), 0) + 1 FROM residents");
    $nextResNum = (int)$seqStmt->fetchColumn();
    $newResCode = sprintf("RES-%s-%04d", date('Y'), $nextResNum);

    $parts = explode(' ', $currentUser['full_name'] ?? 'Citizen Resident');
    $first = $parts[0] ?? 'Citizen';
    $last = count($parts) > 1 ? end($parts) : 'Resident';

    $insRes = $pdo->prepare("
        INSERT INTO residents (
            user_id, resident_code, first_name, last_name, birth_date,
            sex, civil_status, citizenship, purok_zone, street_address,
            residency_start_date, contact_number, is_verified, created_at
        ) VALUES (
            :uid, :code, :fn, :ln, '1995-01-01',
            'Male', 'Single', 'Filipino', 'Purok 1', 'Santa Veronica District, Guimba',
            '2020-01-01', :contact, 1, NOW()
        )
    ");
    $insRes->execute([
        ':uid' => $userId,
        ':code' => $newResCode,
        ':fn' => $first,
        ':ln' => $last,
        ':contact' => $currentUser['contact_number'] ?? null
    ]);
    $residentId = (int)$pdo->lastInsertId();

    $resStmt->execute([':uid' => $userId]);
    $resident = $resStmt->fetch();
}

$residentId = (int)$resident['resident_id'];
$_SESSION['resident_id'] = $residentId;
$_SESSION['resident_code'] = $resident['resident_code'];

// Fetch all active services for the application dropdown
$services = getActiveServices($pdo);

// -----------------------------------------------------------------------------
// POST ACTION 1: SUBMIT ONLINE APPLICATION (intake_channel = 'Online Portal')
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_online') {
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');

    $service = getService($pdo, $serviceId);

    if (!$service) {
        setFlash('danger', 'Invalid service selected. Please choose from the official catalog.');
    } elseif (empty($purpose)) {
        setFlash('danger', 'Please specify the purpose of your application.');
    } else {
        // Enforce RA 11261 (First-Time Jobseeker) validations
        $isFtJobseeker = ($service['service_code'] === 'CERT-FTJ');
        $ra11261Valid = true;

        if ($isFtJobseeker) {
            $raOath = isset($_POST['ra11261_oath']) && $_POST['ra11261_oath'] === '1';
            $raFilipino = isset($_POST['ra11261_filipino']) && $_POST['ra11261_filipino'] === '1';
            $raResidency = isset($_POST['ra11261_residency']) && $_POST['ra11261_residency'] === '1';
            $raTarget = trim($_POST['ra11261_target_purpose'] ?? '');

            if (!$raOath || !$raFilipino || !$raResidency) {
                $ra11261Valid = false;
                setFlash('danger', 'Under Republic Act No. 11261, you must swear the Oath of Undertaking, certify Filipino citizenship, and attest at least 6 months residency.');
            }
        }

        if ($ra11261Valid) {
            $pdo->beginTransaction();
            try {
                // Determine fee and exemption
                $defaultFee = (float)$service['default_fee'];
                $isExempt = ($isFtJobseeker || $service['service_code'] === 'CERT-IND' || $service['service_code'] === 'CERT-SOL' || $service['service_code'] === 'CERT-NOI' || $defaultFee <= 0) ? 1 : 0;
                $feeAmount = $isExempt ? 0.00 : $defaultFee;

                // 1. Generate Application Reference Number: APP-YYYY-XXXX (matches WinForms)
                $nextAppSql = "SELECT COALESCE(MAX(application_id), 0) + 1 FROM applications";
                $nextAppId = (int)$pdo->query($nextAppSql)->fetchColumn();
                $refNumber = sprintf("APP-%s-%04d", date('Y'), $nextAppId);

                // Build submitted data JSON
                $submittedData = [
                    'source' => 'Online Portal',
                    'service_code' => $service['service_code'],
                    'service_name' => $service['service_name'],
                    'citizen_username' => $currentUser['username'],
                    'submitted_ip' => 'WEB-PORTAL',
                    'timestamp' => date('Y-m-d H:i:s')
                ];

                if ($isFtJobseeker) {
                    $submittedData['ra11261_eligibility'] = [
                        'oath_of_undertaking' => true,
                        'filipino_citizen' => true,
                        'residency_qualifies_6_months' => true,
                        'intended_pre_employment' => $raTarget ?: 'Pre-employment requirements',
                        'retention_acknowledged' => true
                    ];
                }

                // If business clearance
                if ($service['service_code'] === 'PERM-BUS') {
                    $submittedData['business_details'] = [
                        'business_name' => trim($_POST['business_name'] ?? ''),
                        'business_type' => trim($_POST['business_type'] ?? ''),
                        'business_location' => trim($_POST['business_location'] ?? '')
                    ];
                }

                $jsonPayload = json_encode($submittedData, JSON_UNESCAPED_UNICODE);

                // 2. Insert into applications (intake_channel = 'Online Portal')
                $appInsert = $pdo->prepare("
                    INSERT INTO applications (
                        reference_number, resident_id, service_id, intake_channel,
                        purpose, submitted_data_json, status, encoded_by_user_id,
                        submitted_at, updated_at
                    ) VALUES (
                        :ref, :resId, :svcId, 'Online Portal',
                        :purpose, :json, 'Submitted', :encId,
                        NOW(), NOW()
                    )
                ");
                $appInsert->execute([
                    ':ref' => $refNumber,
                    ':resId' => $residentId,
                    ':svcId' => $service['service_id'],
                    ':purpose' => $purpose,
                    ':json' => $jsonPayload,
                    ':encId' => $userId
                ]);
                $newAppId = (int)$pdo->lastInsertId();

                // 3. Insert into payments
                $payInsert = $pdo->prepare("
                    INSERT INTO payments (
                        application_id, amount, is_fee_exempt, or_number,
                        payment_channel, payment_status, collected_by_user_id,
                        paid_at, created_at
                    ) VALUES (
                        :aid, :amt, :exempt, NULL,
                        :channel, :pstatus, :collId,
                        :paidAt, NOW()
                    )
                ");
                $payInsert->execute([
                    ':aid' => $newAppId,
                    ':amt' => $feeAmount,
                    ':exempt' => $isExempt,
                    ':channel' => $isExempt ? 'Exempt' : 'Cash Counter',
                    ':pstatus' => $isExempt ? 'Exempt' : 'Unpaid',
                    ':collId' => $isExempt ? $userId : null,
                    ':paidAt' => $isExempt ? date('Y-m-d H:i:s') : null
                ]);
                $newPayId = (int)$pdo->lastInsertId();

                // 4. Generate Queue Ticket (Unified Queue with C# WinForms)
                $isPriority = ($resident['is_senior_citizen'] || $resident['is_pwd']);
                $priorityRank = $isPriority ? 5 : 1;
                $priorityLevel = $resident['is_senior_citizen'] ? 'Senior Citizen' : ($resident['is_pwd'] ? 'PWD' : 'Regular');
                $prefix = $priorityRank >= 5 ? 'P' : 'R';

                $countStmt = $pdo->prepare("
                    SELECT COALESCE(MAX(CAST(SUBSTRING(ticket_number, 3) AS UNSIGNED)), 0) + 1
                    FROM queue_tickets
                    WHERE queue_date = CURDATE() AND ticket_number LIKE :pre
                ");
                $countStmt->execute([':pre' => $prefix . '-%']);
                $nextTicketSeq = (int)$countStmt->fetchColumn();
                $ticketNumber = sprintf("%s-%03d", $prefix, $nextTicketSeq);

                $serviceType = $isExempt ? 'Documents' : 'Payment';
                $counterNumber = $isExempt ? 'Counter 1 (Front Desk)' : 'Counter 2 (Treasurer)';

                $queueInsert = $pdo->prepare("
                    INSERT INTO queue_tickets (
                        ticket_number, application_id, resident_id, service_type,
                        priority_level, priority_rank, status, counter_number,
                        estimated_wait_minutes, queue_date, created_at
                    ) VALUES (
                        :ticket, :aid, :rid, :stype,
                        :plevel, :prank, 'Waiting', :counter,
                        10, CURDATE(), NOW()
                    )
                ");
                $queueInsert->execute([
                    ':ticket' => $ticketNumber,
                    ':aid' => $newAppId,
                    ':rid' => $residentId,
                    ':stype' => $serviceType,
                    ':plevel' => $priorityLevel,
                    ':prank' => $priorityRank,
                    ':counter' => $counterNumber
                ]);
                $newTicketId = (int)$pdo->lastInsertId();

                // 5. Populate initial missing requirements checklist
                $reqItems = [
                    'Valid Government-Issued ID / Resident ID'
                ];
                if ($isFtJobseeker) {
                    $reqItems[] = 'Barangay Oath of Undertaking (RA 11261)';
                    $reqItems[] = 'Proof of Residency (>= 6 Months)';
                } elseif ($service['service_code'] === 'CERT-IND') {
                    $reqItems[] = 'Certificate of Indigency Supporting Documents';
                } elseif ($service['service_code'] === 'PERM-BUS') {
                    $reqItems[] = 'Business Registration / DTI / SEC Document';
                    $reqItems[] = 'Locational Sketch / Lease Contract';
                }

                $reqInsert = $pdo->prepare("
                    INSERT INTO application_requirements (
                        application_id, requirement_name, upload_status, verification_result
                    ) VALUES (:aid, :reqName, 'Missing', 'Pending')
                ");
                foreach ($reqItems as $reqName) {
                    $reqInsert->execute([':aid' => $newAppId, ':reqName' => $reqName]);
                }

                // 6. Strict Rule 2: Audit Logs with ip_or_device = 'WEB-PORTAL'
                logAudit(
                    $pdo,
                    $userId,
                    'CREATE_APPLICATION_ONLINE',
                    'applications',
                    $newAppId,
                    null,
                    [
                        'reference_number' => $refNumber,
                        'service_code' => $service['service_code'],
                        'intake_channel' => 'Online Portal',
                        'is_exempt' => (bool)$isExempt,
                        'queue_ticket' => $ticketNumber
                    ]
                );

                logAudit(
                    $pdo,
                    $userId,
                    'ISSUE_QUEUE_TICKET',
                    'queue_tickets',
                    $newTicketId,
                    null,
                    [
                        'ticket_number' => $ticketNumber,
                        'priority_level' => $priorityLevel,
                        'rank' => $priorityRank,
                        'application_id' => $newAppId
                    ]
                );

                $pdo->commit();

                setFlash('success', "Application <strong>{$refNumber}</strong> submitted successfully! Your Live Queue Ticket is <strong>{$ticketNumber}</strong>. Please upload your requirements below.");
                header("Location: dashboard.php#upload");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('danger', 'Failed to submit application: ' . $e->getMessage());
                error_log("Apply online error: " . $e->getMessage());
            }
        }
    }
}

// -----------------------------------------------------------------------------
// POST ACTION 2: UPLOAD REQUIREMENT FILE (Saves to ../uploads/)
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_requirement') {
    $appId = (int)($_POST['application_id'] ?? 0);
    $reqName = trim($_POST['requirement_name'] ?? 'Supporting Document');
    $reqId = !empty($_POST['requirement_id']) ? (int)$_POST['requirement_id'] : null;

    // Check application belongs to this resident
    $appCheck = $pdo->prepare("SELECT application_id, reference_number FROM applications WHERE application_id = :aid AND resident_id = :rid LIMIT 1");
    $appCheck->execute([':aid' => $appId, ':rid' => $residentId]);
    $appRecord = $appCheck->fetch();

    if (!$appRecord) {
        setFlash('danger', 'Invalid application selected.');
    } elseif (!isset($_FILES['requirement_file']) || $_FILES['requirement_file']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['requirement_file']['error'] ?? 'No file';
        setFlash('danger', 'File upload error (Code: ' . htmlspecialchars((string)$errorCode) . '). Please select a valid document.');
    } else {
        $file = $_FILES['requirement_file'];
        $origName = basename($file['name']);
        $fileSize = $file['size'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];

        if (!in_array($ext, $allowedExts)) {
            setFlash('danger', 'Invalid file type. Allowed formats: JPG, PNG, PDF, and WebP.');
        } elseif ($fileSize > 10 * 1024 * 1024) { // 10MB limit
            setFlash('danger', 'File size exceeds maximum allowed limit of 10MB.');
        } else {
            // Strict Rule 2: Save to ../uploads/ (outside web-version/)
            $targetDir = realpath(UPLOAD_DIR);
            if (!$targetDir) {
                // Create directory if not exists
                @mkdir(UPLOAD_DIR, 0777, true);
                $targetDir = realpath(UPLOAD_DIR) ?: UPLOAD_DIR;
            }

            // Generate safe filename: req_{app_id}_{timestamp}_{random}.ext
            $safeFileName = sprintf("req_%d_%s_%s.%s", $appId, time(), bin2hex(random_bytes(4)), $ext);
            $destinationPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safeFileName;

            if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
                // Strict Rule 2: Calculate SHA-256 Checksum of the uploaded file
                $fileChecksum = hash_file('sha256', $destinationPath);

                // Relative path outside web-root stored in DB
                $relativeRef = 'uploads/' . $safeFileName;

                $pdo->beginTransaction();
                try {
                    if ($reqId) {
                        // Update existing requirement record
                        $updReq = $pdo->prepare("
                            UPDATE application_requirements
                            SET upload_status = 'Uploaded',
                                encrypted_file_ref = :ref,
                                file_checksum_sha256 = :sha,
                                verification_result = 'Pending',
                                remarks = :origName
                            WHERE requirement_id = :rid AND application_id = :aid
                        ");
                        $updReq->execute([
                            ':ref' => $relativeRef,
                            ':sha' => $fileChecksum,
                            ':origName' => $origName,
                            ':rid' => $reqId,
                            ':aid' => $appId
                        ]);
                        $savedReqId = $reqId;
                    } else {
                        // Insert new requirement record
                        $insReq = $pdo->prepare("
                            INSERT INTO application_requirements (
                                application_id, requirement_name, upload_status,
                                encrypted_file_ref, file_checksum_sha256, verification_result, remarks
                            ) VALUES (
                                :aid, :name, 'Uploaded',
                                :ref, :sha, 'Pending', :origName
                            )
                        ");
                        $insReq->execute([
                            ':aid' => $appId,
                            ':name' => $reqName,
                            ':ref' => $relativeRef,
                            ':sha' => $fileChecksum,
                            ':origName' => $origName
                        ]);
                        $savedReqId = (int)$pdo->lastInsertId();
                    }

                    // Update application updated_at
                    $updApp = $pdo->prepare("UPDATE applications SET updated_at = NOW() WHERE application_id = :aid");
                    $updApp->execute([':aid' => $appId]);

                    // Strict Rule 2: Audit log with ip_or_device = 'WEB-PORTAL'
                    logAudit(
                        $pdo,
                        $userId,
                        'UPLOAD_REQUIREMENT',
                        'application_requirements',
                        $savedReqId,
                        null,
                        [
                            'application_ref' => $appRecord['reference_number'],
                            'file_name' => $safeFileName,
                            'checksum_sha256' => $fileChecksum,
                            'relative_ref' => $relativeRef,
                            'ip_or_device' => 'WEB-PORTAL'
                        ]
                    );

                    $pdo->commit();
                    setFlash('success', "Requirement <strong>" . htmlspecialchars($reqName) . "</strong> uploaded successfully! (SHA-256: <code>" . substr($fileChecksum, 0, 16) . "...</code>)");
                    header("Location: dashboard.php#app_" . $appId);
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    setFlash('danger', 'Database error recording requirement: ' . $e->getMessage());
                    error_log("Upload req error: " . $e->getMessage());
                }
            } else {
                setFlash('danger', 'Failed to move uploaded file to server storage directory.');
            }
        }
    }
}

// -----------------------------------------------------------------------------
// DATA FETCHING: APPLICATIONS & LIVE QUEUE TICKETS
// -----------------------------------------------------------------------------

// 1. Citizen's Application History with Documents and Payments
$myAppsStmt = $pdo->prepare("
    SELECT 
        a.application_id,
        a.reference_number,
        a.purpose,
        a.submitted_data_json,
        a.status as app_status,
        a.rejection_reason,
        a.submitted_at,
        a.updated_at,
        s.service_id,
        s.service_code,
        s.service_name,
        s.category as service_category,
        s.default_fee,
        p.amount as pay_amount,
        p.payment_status,
        p.is_fee_exempt,
        d.document_id,
        d.control_number,
        d.qr_verification_token,
        d.signatory_name,
        d.signatory_position,
        d.issued_date,
        d.expiry_date,
        d.release_status as doc_status
    FROM applications a
    JOIN services s ON a.service_id = s.service_id
    LEFT JOIN payments p ON a.application_id = p.application_id
    LEFT JOIN documents d ON a.application_id = d.application_id
    WHERE a.resident_id = :rid
    ORDER BY a.submitted_at DESC
");
$myAppsStmt->execute([':rid' => $residentId]);
$myApplications = $myAppsStmt->fetchAll();

// 2. Fetch requirements for each application
$appRequirementsMap = [];
if (!empty($myApplications)) {
    $appIds = array_column($myApplications, 'application_id');
    $inClause = implode(',', array_map('intval', $appIds));
    $reqList = $pdo->query("
        SELECT requirement_id, application_id, requirement_name, upload_status,
               encrypted_file_ref, file_checksum_sha256, verification_result, remarks
        FROM application_requirements
        WHERE application_id IN ($inClause)
        ORDER BY requirement_id ASC
    ")->fetchAll();

    foreach ($reqList as $r) {
        $appRequirementsMap[$r['application_id']][] = $r;
    }
}

// 3. Citizen's Active Queue Ticket for Today
$myQueueStmt = $pdo->prepare("
    SELECT ticket_id, ticket_number, application_id, service_type,
           priority_level, priority_rank, status, counter_number,
           estimated_wait_minutes, called_at, completed_at, created_at
    FROM queue_tickets
    WHERE resident_id = :rid AND queue_date = CURDATE()
    ORDER BY ticket_id DESC
    LIMIT 1
");
$myQueueStmt->execute([':rid' => $residentId]);
$myActiveTicket = $myQueueStmt->fetch();

$aheadCount = 0;
$myQueuePosition = 1;
if ($myActiveTicket && in_array($myActiveTicket['status'], ['Waiting', 'Requeued'])) {
    $aheadStmt = $pdo->prepare("
        SELECT COUNT(*) FROM queue_tickets
        WHERE queue_date = CURDATE()
          AND status IN ('Waiting', 'Requeued')
          AND (
              priority_rank > :rank
              OR (priority_rank = :rank AND ticket_id < :tid)
          )
    ");
    $aheadStmt->execute([
        ':rank' => $myActiveTicket['priority_rank'],
        ':tid' => $myActiveTicket['ticket_id']
    ]);
    $aheadCount = (int)$aheadStmt->fetchColumn();
    $myQueuePosition = $aheadCount + 1;
}

// 4. Overall Barangay "Now Serving" Board for Today
$nowServingDesk = $pdo->query("
    SELECT ticket_number FROM queue_tickets
    WHERE queue_date = CURDATE() AND status = 'Now Serving' AND (counter_number LIKE '%Counter 1%' OR counter_number LIKE '%Desk%')
    ORDER BY called_at DESC LIMIT 1
")->fetchColumn();

$nowServingTreasury = $pdo->query("
    SELECT ticket_number FROM queue_tickets
    WHERE queue_date = CURDATE() AND status = 'Now Serving' AND (counter_number LIKE '%Counter 2%' OR counter_number LIKE '%Treasurer%')
    ORDER BY called_at DESC LIMIT 1
")->fetchColumn();

$totalWaitingToday = (int)$pdo->query("
    SELECT COUNT(*) FROM queue_tickets
    WHERE queue_date = CURDATE() AND status IN ('Waiting', 'Requeued')
")->fetchColumn();

$preselectedServiceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
if (!$preselectedServiceId && isset($_GET['service'])) {
    $svcCheck = getService($pdo, $_GET['service']);
    if ($svcCheck) $preselectedServiceId = (int)$svcCheck['service_id'];
}

$pageTitle = "Citizen Self-Service Portal";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-3">
    <!-- Resident Profile Greeting Card -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-3 p-md-4">
            <div class="row align-items-center g-3">
                <div class="col-auto">
                    <div class="navbar-brand-seal bg-primary text-white" style="width: 58px; height: 58px; font-size: 1.7rem;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                </div>
                <div class="col">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h4 class="fw-bold text-dark mb-0"><?= e($resident['first_name'] . ' ' . $resident['last_name']) ?></h4>
                        <span class="badge bg-primary text-uppercase"><?= e($resident['resident_code']) ?></span>
                        <?php if ($resident['is_senior_citizen']): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>Senior Citizen (Priority)</span>
                        <?php endif; ?>
                        <?php if ($resident['is_pwd']): ?>
                            <span class="badge bg-info text-dark"><i class="bi bi-person-wheelchair me-1"></i>PWD (Priority)</span>
                        <?php endif; ?>
                        <?php if ($resident['is_solo_parent']): ?>
                            <span class="badge bg-secondary"><i class="bi bi-people me-1"></i>Solo Parent</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($resident['street_address']) ?>, <?= e($resident['purok_zone']) ?>, Santa Veronica District &bull;
                        <i class="bi bi-telephone-fill text-success ms-2 me-1"></i> <?= e($resident['contact_number'] ?: 'No phone recorded') ?>
                    </div>
                </div>
                <div class="col-12 col-md-auto text-md-end">
                    <a href="#apply" class="btn btn-gold btn-sm text-dark fw-bold me-1">
                        <i class="bi bi-plus-circle-fill"></i> New Online Request
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Queue Board Card (Rule 4: "Now Serving" + Ticket Position) -->
    <div id="live_queue_section" class="queue-board-card mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom border-secondary pb-3 mb-3">
            <div class="d-flex align-items-center gap-2">
                <span class="now-serving-badge"><i class="bi bi-broadcast"></i> LIVE QUEUE BOARD</span>
                <span class="text-white-50 small d-none d-sm-inline">&bull; Real-time Hall Counter Status (<?= date('F j, Y') ?>)</span>
            </div>
            <div class="text-end text-white-50 small">
                <i class="bi bi-arrow-repeat text-info me-1"></i> Auto-refreshes live
            </div>
        </div>

        <div class="row g-3 text-center align-items-stretch">
            <!-- Counter 1: Front Desk / Documents -->
            <div class="col-6 col-md-3">
                <div class="bg-dark bg-opacity-50 p-3 rounded-3 border border-secondary h-100">
                    <small class="text-white-50 text-uppercase d-block mb-1">Counter 1 &bull; Front Desk</small>
                    <div id="counter_1_ticket" class="ticket-number-display mb-1">
                        <?= e($nowServingDesk ?: '--') ?>
                    </div>
                    <small class="text-info">Documents & Releasing</small>
                </div>
            </div>

            <!-- Counter 2: Treasurer / Payments -->
            <div class="col-6 col-md-3">
                <div class="bg-dark bg-opacity-50 p-3 rounded-3 border border-secondary h-100">
                    <small class="text-white-50 text-uppercase d-block mb-1">Counter 2 &bull; Treasury</small>
                    <div id="counter_2_ticket" class="ticket-number-display mb-1" style="color: #4ade80; text-shadow: 0 0 20px rgba(74, 222, 128, 0.4);">
                        <?= e($nowServingTreasury ?: '--') ?>
                    </div>
                    <small class="text-success">Payments & Fees</small>
                </div>
            </div>

            <!-- Total Waiting in Queue -->
            <div class="col-6 col-md-2">
                <div class="bg-dark bg-opacity-50 p-3 rounded-3 border border-secondary h-100 d-flex flex-column justify-content-center">
                    <small class="text-white-50 text-uppercase d-block mb-1">In Line Today</small>
                    <div id="total_waiting_count" class="fs-1 fw-bold text-warning mb-0">
                        <?= $totalWaitingToday ?>
                    </div>
                    <small class="text-white-50">Citizens Waiting</small>
                </div>
            </div>

            <!-- Citizen's Personal Queue Position -->
            <div class="col-12 col-md-4">
                <div class="my-ticket-highlight h-100 d-flex flex-column justify-content-center">
                    <?php if ($myActiveTicket): ?>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-warning text-dark text-uppercase"><?= e($myActiveTicket['priority_level']) ?></span>
                            <span class="badge bg-light text-primary"><?= e($myActiveTicket['status']) ?></span>
                        </div>
                        <div class="small text-white-50">Your Active Ticket Today:</div>
                        <div class="fs-2 fw-black text-warning font-monospace fw-bold mb-1">
                            <?= e($myActiveTicket['ticket_number']) ?>
                        </div>
                        <div id="my_queue_position" class="small">
                            <?php if ($myActiveTicket['status'] === 'Now Serving'): ?>
                                <span class="badge bg-danger p-2 fs-6"><i class="bi bi-bell-fill"></i> NOW SERVING AT <?= e($myActiveTicket['counter_number'] ?: 'Counter') ?>!</span>
                            <?php elseif ($myActiveTicket['status'] === 'Waiting'): ?>
                                You are <strong>#<?= $myQueuePosition ?></strong> in line (<?= $aheadCount ?> tickets ahead)
                            <?php elseif ($myActiveTicket['status'] === 'Completed'): ?>
                                <span class="badge bg-success p-2"><i class="bi bi-check-circle"></i> Service Completed</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-2">
                            <i class="bi bi-ticket-perforated fs-2 text-white-50 mb-1"></i>
                            <div class="fw-semibold">No Active Ticket Today</div>
                            <small class="text-white-50">Submit an online application below to receive an automated queue ticket.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 bg-white p-2 rounded-3 shadow-sm" id="dashboardTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill fw-semibold" id="tab-my-apps" data-bs-toggle="pill" data-bs-target="#panel-apps" type="button" role="tab">
                <i class="bi bi-file-earmark-text me-1"></i> My Applications (<?= count($myApplications) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill fw-semibold" id="tab-apply" data-bs-toggle="pill" data-bs-target="#panel-apply" type="button" role="tab">
                <i class="bi bi-pencil-square me-1"></i> Apply Online
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill fw-semibold" id="tab-upload" data-bs-toggle="pill" data-bs-target="#panel-upload" type="button" role="tab">
                <i class="bi bi-cloud-arrow-up me-1"></i> Upload Requirements
            </button>
        </li>
    </ul>

    <!-- Tab Panels -->
    <div class="tab-content" id="dashboardTabContent">
        
        <!-- ===================================================================
             PANEL 1: MY APPLICATIONS & STATUS TRACKING
             =================================================================== -->
        <div class="tab-pane fade show active" id="panel-apps" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">Application Tracker & History</h5>
                <small class="text-muted">Total: <?= count($myApplications) ?> record(s)</small>
            </div>

            <?php if (empty($myApplications)): ?>
                <div class="card border-0 shadow-sm text-center py-5 rounded-4 bg-white">
                    <div class="card-body">
                        <i class="bi bi-folder2-open text-muted" style="font-size: 3.5rem;"></i>
                        <h5 class="fw-bold text-dark mt-3">No Applications Yet</h5>
                        <p class="text-muted small mb-3">You have not submitted any document requests through the online portal.</p>
                        <button class="btn btn-primary" onclick="document.getElementById('tab-apply').click();">
                            <i class="bi bi-plus-circle me-1"></i> Apply for Your First Document
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($myApplications as $app): ?>
                        <?php 
                            $appId = (int)$app['application_id'];
                            $reqs = $appRequirementsMap[$appId] ?? [];
                            $isFtJob = ($app['service_code'] === 'CERT-FTJ');

                            $statusBadgeClass = match($app['app_status']) {
                                'Submitted' => 'bg-primary',
                                'For Review' => 'bg-warning text-dark',
                                'For Payment' => 'bg-warning text-dark',
                                'For Signature' => 'bg-info text-dark',
                                'Ready for Claim' => 'bg-success',
                                'Released' => 'bg-success',
                                'Rejected' => 'bg-danger',
                                'Cancelled' => 'bg-secondary',
                                default => 'bg-secondary'
                            };

                            // Status step index (0 to 4)
                            $stepIndex = match($app['app_status']) {
                                'Submitted' => 1,
                                'For Review' => 2,
                                'For Payment', 'For Signature' => 3,
                                'Ready for Claim', 'Released' => 4,
                                default => 1
                            };
                        ?>
                        <div class="col-12" id="app_<?= $appId ?>">
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                                <div class="card-header bg-light py-3 px-3 px-md-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <span class="badge bg-dark font-monospace me-2"><?= e($app['reference_number']) ?></span>
                                        <strong class="text-dark fs-6"><?= e($app['service_name']) ?></strong>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge <?= $statusBadgeClass ?> px-3 py-2 fs-6">
                                            <?= e($app['app_status']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="card-body p-3 p-md-4">
                                    <!-- Mobile Status Timeline -->
                                    <ul class="status-steps">
                                        <li class="step-item <?= $stepIndex >= 1 ? ($stepIndex > 1 ? 'completed' : 'active') : '' ?>">
                                            <div class="step-circle"><?= $stepIndex > 1 ? '<i class="bi bi-check"></i>' : '1' ?></div>
                                            <div class="step-label">Submitted</div>
                                        </li>
                                        <li class="step-item <?= $stepIndex >= 2 ? ($stepIndex > 2 ? 'completed' : 'active') : '' ?>">
                                            <div class="step-circle"><?= $stepIndex > 2 ? '<i class="bi bi-check"></i>' : '2' ?></div>
                                            <div class="step-label">Under Review</div>
                                        </li>
                                        <li class="step-item <?= $stepIndex >= 3 ? ($stepIndex > 3 ? 'completed' : 'active') : '' ?>">
                                            <div class="step-circle"><?= $stepIndex > 3 ? '<i class="bi bi-check"></i>' : '3' ?></div>
                                            <div class="step-label">Payment / Sign</div>
                                        </li>
                                        <li class="step-item <?= $stepIndex >= 4 ? 'completed' : '' ?>">
                                            <div class="step-circle"><?= $stepIndex >= 4 ? '<i class="bi bi-check"></i>' : '4' ?></div>
                                            <div class="step-label">Ready / Released</div>
                                        </li>
                                    </ul>

                                    <div class="row g-3 mb-3 small">
                                        <div class="col-md-6">
                                            <div class="text-muted">Purpose of Request:</div>
                                            <div class="fw-semibold text-dark"><?= e($app['purpose']) ?></div>
                                            <?php if ($isFtJob): ?>
                                                <span class="badge bg-success mt-1"><i class="bi bi-award me-1"></i>RA 11261 First-Time Jobseeker (Fee Waived)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-muted">Payment Status:</div>
                                            <div>
                                                <?php if ($app['is_fee_exempt']): ?>
                                                    <span class="badge bg-success">Exempt (FREE)</span>
                                                <?php else: ?>
                                                    <span class="badge <?= $app['payment_status'] === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                        <?= e($app['payment_status'] ?: 'Unpaid') ?> (₱<?= number_format((float)$app['pay_amount'], 2) ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-muted">Submitted On:</div>
                                            <div class="text-dark"><?= date('M j, Y h:i A', strtotime($app['submitted_at'])) ?></div>
                                        </div>
                                    </div>

                                    <?php if ($app['app_status'] === 'Rejected' && $app['rejection_reason']): ?>
                                        <div class="alert alert-danger p-2 small mb-3">
                                            <strong><i class="bi bi-x-circle-fill me-1"></i>Rejection Reason:</strong> <?= e($app['rejection_reason']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Generated Document & QR Verification Link -->
                                    <?php if ($app['control_number']): ?>
                                        <div class="bg-light p-3 rounded-3 border border-success mb-3">
                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                <div>
                                                    <span class="badge bg-success mb-1"><i class="bi bi-check-circle-fill me-1"></i>Official Document Issued</span>
                                                    <div class="fw-bold text-dark">Control #: <code><?= e($app['control_number']) ?></code></div>
                                                    <small class="text-muted">Signatory: <?= e($app['signatory_name'] ?: 'Hon. Roberto Santos') ?> (<?= e($app['signatory_position'] ?: 'Punong Barangay') ?>)</small>
                                                </div>
                                                <div>
                                                    <a href="verify.php?token=<?= urlencode($app['qr_verification_token']) ?>" class="btn btn-outline-success btn-sm fw-bold">
                                                        <i class="bi bi-qr-code-scan"></i> View QR Verification
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Uploaded Requirements Checklist -->
                                    <div class="border rounded-3 p-3 bg-light">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong class="text-primary small text-uppercase"><i class="bi bi-paperclip me-1"></i>Requirements Checklist</strong>
                                            <button class="btn btn-sm btn-outline-primary py-0" type="button" data-bs-toggle="collapse" data-bs-target="#req_list_<?= $appId ?>">
                                                Toggle Checklist (<?= count($reqs) ?>)
                                            </button>
                                        </div>

                                        <div class="collapse show" id="req_list_<?= $appId ?>">
                                            <?php if (empty($reqs)): ?>
                                                <div class="text-muted small">No specific documentary requirements tracked.</div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-borderless align-middle mb-0 small">
                                                        <thead>
                                                            <tr class="text-muted border-bottom">
                                                                <th>Requirement</th>
                                                                <th>Upload Status</th>
                                                                <th>SHA-256 Checksum</th>
                                                                <th>Verification</th>
                                                                <th>Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($reqs as $rq): ?>
                                                                <tr>
                                                                    <td class="fw-semibold text-dark"><?= e($rq['requirement_name']) ?></td>
                                                                    <td>
                                                                        <?php if ($rq['upload_status'] === 'Uploaded'): ?>
                                                                            <span class="badge bg-success"><i class="bi bi-check2"></i> Uploaded</span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation"></i> Missing</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($rq['file_checksum_sha256']): ?>
                                                                            <div class="checksum-box d-flex align-items-center gap-1">
                                                                                <span><?= substr($rq['file_checksum_sha256'], 0, 16) ?>...</span>
                                                                                <button class="btn btn-link btn-sm p-0 btn-copy-text" data-clipboard-text="<?= e($rq['file_checksum_sha256']) ?>" title="Copy full SHA-256">
                                                                                    <i class="bi bi-clipboard"></i>
                                                                                </button>
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">--</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge <?= $rq['verification_result'] === 'Verified' ? 'bg-success' : ($rq['verification_result'] === 'Rejected' ? 'bg-danger' : 'bg-secondary') ?>">
                                                                            <?= e($rq['verification_result']) ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" onclick="openRequirementModal(<?= $appId ?>, '<?= e(addslashes($rq['requirement_name'])) ?>', <?= (int)$rq['requirement_id'] ?>)">
                                                                            <i class="bi bi-upload"></i> <?= $rq['upload_status'] === 'Uploaded' ? 'Re-upload' : 'Upload' ?>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===================================================================
             PANEL 2: APPLY ONLINE (intake_channel = 'Online Portal')
             =================================================================== -->
        <div class="tab-pane fade" id="panel-apply" role="tabpanel">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="card border-0 shadow-sm rounded-4 bg-white">
                        <div class="card-header bg-primary text-white py-3 px-4">
                            <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square me-2"></i>Online Document Application</h5>
                            <small class="text-info">Intake Channel: Online Portal &bull; Auto-Assigned Queue Ticket</small>
                        </div>

                        <div class="card-body p-4">
                            <form action="dashboard.php" method="POST" id="online_application_form">
                                <input type="hidden" name="action" value="apply_online">
                                <input type="hidden" name="is_exempt" id="service_is_exempt" value="0">

                                <div class="mb-3">
                                    <label for="apply_service_id" class="form-label fw-bold">Select Barangay Document or Permit <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-lg" id="apply_service_id" name="service_id" required>
                                        <option value="">-- Choose from 14 official services --</option>
                                        <?php foreach ($services as $s): ?>
                                            <option value="<?= $s['service_id'] ?>"
                                                data-code="<?= e($s['service_code']) ?>"
                                                data-fee="<?= (float)$s['default_fee'] ?>"
                                                data-validity="<?= e($s['validity_days']) ?>"
                                                data-time="<?= e($s['processing_time_label']) ?>"
                                                data-reqs="<?= e($s['requirements_text']) ?>"
                                                <?= $preselectedServiceId === (int)$s['service_id'] ? 'selected' : '' ?>>
                                                [<?= e($s['service_code']) ?>] <?= e($s['service_name']) ?> — <?= $s['default_fee'] <= 0 ? 'FREE' : '₱' . number_format($s['default_fee'], 2) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Dynamic Service Details Card -->
                                <div id="service_info_card" class="card bg-light border p-3 rounded-3 mb-3 d-none">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-6 col-md-3">
                                            <small class="text-muted d-block">FEE</small>
                                            <div id="service_fee_badge">--</div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <small class="text-muted d-block">PROCESSING TIME</small>
                                            <strong id="service_time_text" class="text-dark">Same Day</strong>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <small class="text-muted d-block">VALIDITY PERIOD</small>
                                            <strong id="service_validity_text" class="text-dark">180 days</strong>
                                        </div>
                                        <div class="col-12 mt-2 pt-2 border-top">
                                            <small class="text-muted d-block fw-bold"><i class="bi bi-paperclip me-1"></i>DOCUMENT REQUIREMENTS</small>
                                            <div id="service_requirements_text" class="text-dark small">--</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="apply_purpose" class="form-label fw-bold">Purpose of Request <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="apply_purpose" name="purpose" rows="2" placeholder="e.g. Local employment application, NBI clearance, school scholarship, business permit renewal" required></textarea>
                                </div>

                                <!-- =======================================================
                                     STRICT RULE: RA 11261 (First-Time Jobseeker) ENFORCEMENT
                                     ======================================================= -->
                                <div id="ra11261_fields_section" class="ra11261-box mb-4 d-none">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <i class="bi bi-patch-check-fill text-warning fs-4"></i>
                                        <h6 class="fw-bold text-dark mb-0">Republic Act No. 11261 (First-Time Jobseekers Assistance Act)</h6>
                                    </div>
                                    <p class="small text-muted mb-3">
                                        As a first-time Filipino jobseeker, you are exempt from barangay certification fees. Pursuant to law, you must execute the mandatory declarations below:
                                    </p>

                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="ra11261_oath" name="ra11261_oath" value="1">
                                        <label class="form-check-label small fw-semibold" for="ra11261_oath">
                                            <strong>Oath of Undertaking:</strong> I solemnly swear under penalty of law that I am a first-time jobseeker actively seeking employment and have not previously availed of the benefits under RA 11261 from any other government agency. <span class="text-danger">*</span>
                                        </label>
                                    </div>

                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="ra11261_filipino" name="ra11261_filipino" value="1">
                                        <label class="form-check-label small fw-semibold" for="ra11261_filipino">
                                            I declare that I am a bona fide <strong>Filipino Citizen</strong>. <span class="text-danger">*</span>
                                        </label>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="ra11261_residency" name="ra11261_residency" value="1">
                                        <label class="form-check-label small fw-semibold" for="ra11261_residency">
                                            I certify that I have resided in <strong>Barangay Santa Veronica District, Guimba, Nueva Ecija</strong> for at least six (6) continuous months. <span class="text-danger">*</span>
                                        </label>
                                    </div>

                                    <div class="mb-2">
                                        <label for="ra11261_target_purpose" class="form-label small fw-bold">Target Pre-Employment Requirement</label>
                                        <input type="text" class="form-control form-control-sm" id="ra11261_target_purpose" name="ra11261_target_purpose" placeholder="e.g. NBI Clearance, Police Clearance, PhilHealth, Pag-IBIG, SSS, or Employer Submission">
                                    </div>

                                    <div class="alert alert-warning py-2 px-3 small mb-0 mt-2">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                        <strong>Employer Retention Notice (Sec. 5):</strong> The original copy of this certificate will be retained by the employer upon your successful employment.
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg shadow">
                                        <i class="bi bi-send-fill me-1"></i> Submit Application & Generate Queue Ticket
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================================================================
             PANEL 3: UPLOAD REQUIREMENTS (Saves to ../uploads/)
             =================================================================== -->
        <div class="tab-pane fade" id="panel-upload" role="tabpanel">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 bg-white">
                        <div class="card-header bg-primary text-white py-3 px-4">
                            <h5 class="fw-bold mb-0"><i class="bi bi-cloud-arrow-up-fill me-2"></i>Upload Citizen Requirements</h5>
                            <small class="text-info">Saved to secure storage outside web-root &bull; SHA-256 Checksum Verified</small>
                        </div>

                        <div class="card-body p-4">
                            <?php if (empty($myApplications)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle-fill me-2"></i>You have no pending applications. Please submit an application first.
                                </div>
                            <?php else: ?>
                                <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_requirement">

                                    <div class="mb-3">
                                        <label for="upload_app_id" class="form-label fw-bold">Select Application Reference <span class="text-danger">*</span></label>
                                        <select class="form-select" id="upload_app_id" name="application_id" required>
                                            <?php foreach ($myApplications as $a): ?>
                                                <option value="<?= $a['application_id'] ?>">
                                                    <?= e($a['reference_number']) ?> &mdash; <?= e($a['service_name']) ?> (<?= e($a['app_status']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="upload_req_name" class="form-label fw-bold">Requirement Document Name <span class="text-danger">*</span></label>
                                        <select class="form-select mb-2" id="quick_req_name" onchange="if(this.value) document.getElementById('upload_req_name').value = this.value;">
                                            <option value="">-- Choose standard document type or type below --</option>
                                            <option value="Valid Government-Issued ID / Resident ID">Valid Government-Issued ID / Resident ID</option>
                                            <option value="Proof of Residency (Utility Bill / Lease Agreement)">Proof of Residency (Utility Bill / Lease Agreement)</option>
                                            <option value="Barangay Oath of Undertaking (RA 11261)">Barangay Oath of Undertaking (RA 11261)</option>
                                            <option value="Certificate of Indigency Supporting Documents">Certificate of Indigency Supporting Documents</option>
                                            <option value="Business DTI / SEC Registration">Business DTI / SEC Registration</option>
                                            <option value="Locational Sketch / Barangay Clearance">Locational Sketch / Barangay Clearance</option>
                                        </select>
                                        <input type="text" class="form-control" id="upload_req_name" name="requirement_name" placeholder="Enter document name e.g. Valid PhilHealth ID" required>
                                    </div>

                                    <div class="mb-4">
                                        <label for="requirement_file" class="form-label fw-bold">Choose File <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control" id="requirement_file" name="requirement_file" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
                                        <small class="text-muted d-block mt-1">
                                            Supported: PDF, JPG, PNG, WebP (Max: 10MB). Stored securely outside web root with cryptographic integrity verification.
                                        </small>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg shadow">
                                            <i class="bi bi-upload me-1"></i> Upload & Record SHA-256 Checksum
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal for Quick Requirement Upload from Table -->
<div class="modal fade" id="quickUploadModal" tabindex="-1" aria-labelledby="quickUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_requirement">
                <input type="hidden" name="application_id" id="modal_app_id" value="">
                <input type="hidden" name="requirement_id" id="modal_req_id" value="">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="quickUploadModalLabel"><i class="bi bi-upload me-2"></i>Upload Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Requirement Document</label>
                        <input type="text" class="form-control bg-light" id="modal_req_name" name="requirement_name" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="modal_file" class="form-label fw-bold">Choose File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="modal_file" name="requirement_file" accept=".jpg,.jpeg,.png,.pdf,.webp" required>
                        <small class="text-muted">PDF, JPG, PNG, or WebP up to 10MB.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Upload Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRequirementModal(appId, reqName, reqId) {
    document.getElementById('modal_app_id').value = appId;
    document.getElementById('modal_req_name').value = reqName;
    document.getElementById('modal_req_id').value = reqId || '';
    var modal = new bootstrap.Modal(document.getElementById('quickUploadModal'));
    modal.show();
}

// Handle URL hash navigation on load
window.addEventListener('DOMContentLoaded', function() {
    var hash = window.location.hash;
    if (hash === '#apply') {
        var applyTab = document.getElementById('tab-apply');
        if (applyTab) applyTab.click();
    } else if (hash === '#upload') {
        var uploadTab = document.getElementById('tab-upload');
        if (uploadTab) uploadTab.click();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
