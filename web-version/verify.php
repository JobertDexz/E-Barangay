<?php
/**
 * e-Barangay Web Portal - Public QR Verification Page
 * Barangay Santa Veronica District, Guimba, Nueva Ecija
 *
 * Privacy Rule:
 * Shows control number, service, issue/expiry date, signatory, and status
 * WITHOUT exposing private resident details.
 */

require_once __DIR__ . '/includes/db.php';
$pdo = getDb();
$profile = getBarangayProfile($pdo);

$tokenQuery = trim($_GET['token'] ?? '');
$document = null;
$searched = false;
$isExpired = false;

if (!empty($tokenQuery)) {
    $searched = true;

    $stmt = $pdo->prepare("
        SELECT 
            d.document_id,
            d.control_number,
            d.template_version,
            d.qr_verification_token,
            d.signatory_name,
            d.signatory_position,
            d.issued_date,
            d.expiry_date,
            d.is_original_retained,
            d.release_status,
            d.document_hash_sha256,
            d.created_at,
            s.service_code,
            s.service_name,
            s.category as service_category
        FROM documents d
        JOIN applications a ON d.application_id = a.application_id
        JOIN services s ON a.service_id = s.service_id
        WHERE d.qr_verification_token = :t1 OR d.control_number = :t2
        LIMIT 1
    ");
    $stmt->execute([':t1' => $tokenQuery, ':t2' => $tokenQuery]);
    $document = $stmt->fetch();

    if ($document) {
        // Check expiration
        if (!empty($document['expiry_date'])) {
            $expiryTime = strtotime($document['expiry_date']);
            if ($expiryTime < strtotime(date('Y-m-d'))) {
                $isExpired = true;
            }
        }

        // Strict Rule 2: Audit log with ip_or_device = 'WEB-PORTAL'
        $currentUserId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;
        logAudit(
            $pdo,
            $currentUserId,
            'VERIFY_DOCUMENT',
            'documents',
            $document['document_id'],
            null,
            [
                'searched_token' => $tokenQuery,
                'control_number' => $document['control_number'],
                'status' => $document['release_status'],
                'is_expired' => $isExpired
            ]
        );
    }
}

$pageTitle = "Public QR Document Verification";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="text-center mb-4">
        <div class="navbar-brand-seal mx-auto mb-2 text-primary" style="width: 58px; height: 58px; font-size: 1.8rem;">
            <i class="bi bi-qr-code-scan"></i>
        </div>
        <h2 class="fw-bold text-dark mb-1">Official Document Verification</h2>
        <p class="text-muted small max-w-700 mx-auto">
            Public verification portal for documents, permits, and clearances issued by <?= e($profile['barangay_name']) ?>, <?= e($profile['municipality_city']) ?>, <?= e($profile['province']) ?>.
        </p>
    </div>

    <!-- Search Form -->
    <div class="row justify-content-center mb-4">
        <div class="col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                <form action="verify.php" method="GET" class="d-flex flex-column flex-sm-row gap-2">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="token" value="<?= e($tokenQuery) ?>" placeholder="Scan QR or Enter Token / Control No." required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-shield-check"></i> Verify
                    </button>
                </form>
                <div class="small text-muted text-center mt-2">
                    <i class="bi bi-shield-lock-fill text-success"></i> Privacy Safeguard: Citizen personal data is strictly protected under RA 10173.
                </div>
            </div>
        </div>
    </div>

    <!-- Results Section -->
    <?php if ($searched): ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($document): ?>
                    <!-- VERIFIED DOCUMENT FOUND -->
                    <div class="verification-card">
                        <?php if ($isExpired): ?>
                            <div class="verification-header-expired">
                                <div class="security-seal-icon">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                                <h3 class="fw-bold mb-1">EXPIRED BARANGAY DOCUMENT</h3>
                                <p class="mb-0 text-white-50 small">This document was officially issued but has passed its validity period.</p>
                            </div>
                        <?php elseif ($document['release_status'] === 'Revoked'): ?>
                            <div class="verification-header-invalid">
                                <div class="security-seal-icon">
                                    <i class="bi bi-x-octagon-fill"></i>
                                </div>
                                <h3 class="fw-bold mb-1">DOCUMENT REVOKED</h3>
                                <p class="mb-0 text-white-50 small">This document has been officially recalled or cancelled by the Barangay.</p>
                            </div>
                        <?php else: ?>
                            <div class="verification-header-verified">
                                <div class="security-seal-icon">
                                    <i class="bi bi-patch-check-fill"></i>
                                </div>
                                <h3 class="fw-bold mb-1">OFFICIALLY VERIFIED & AUTHENTIC</h3>
                                <p class="mb-0 text-white-50 small">Bona fide government document issued by <?= e($profile['barangay_name']) ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="p-4 p-md-5 bg-white">
                            <!-- Official Authority Card -->
                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 border mb-4">
                                <div class="navbar-brand-seal bg-white text-primary" style="width: 48px; height: 48px; font-size: 1.4rem;">
                                    <i class="bi bi-building-fill"></i>
                                </div>
                                <div>
                                    <strong class="d-block text-dark"><?= e($profile['barangay_name']) ?></strong>
                                    <small class="text-muted"><?= e($profile['municipality_city']) ?>, <?= e($profile['province']) ?> &bull; <?= e($profile['region']) ?></small>
                                </div>
                            </div>

                            <!-- Document Details Table (Strict: No private resident details exposed) -->
                            <table class="table table-bordered align-middle data-table-clean mb-4">
                                <tbody>
                                    <tr>
                                        <th style="width: 35%;">Control Number</th>
                                        <td class="fw-bold font-monospace text-primary fs-6"><?= e($document['control_number']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Service / Document</th>
                                        <td class="fw-semibold text-dark">
                                            <?= e($document['service_name']) ?>
                                            <span class="badge bg-secondary ms-1"><?= e($document['service_code']) ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Document Category</th>
                                        <td><?= e($document['service_category']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date Issued</th>
                                        <td><?= date('F j, Y', strtotime($document['issued_date'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Validity / Expiry Date</th>
                                        <td>
                                            <?php if ($document['expiry_date']): ?>
                                                <span class="<?= $isExpired ? 'text-danger fw-bold' : 'text-success fw-bold' ?>">
                                                    <?= date('F j, Y', strtotime($document['expiry_date'])) ?>
                                                    <?= $isExpired ? '(EXPIRED)' : '(VALID)' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">No fixed expiration</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Signatory Official</th>
                                        <td>
                                            <strong class="text-dark"><?= e($document['signatory_name']) ?></strong><br>
                                            <small class="text-muted"><?= e($document['signatory_position']) ?></small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Official Status</th>
                                        <td>
                                            <span class="badge bg-success fs-6"><?= e($document['release_status']) ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Security QR Token</th>
                                        <td><code class="small"><?= e($document['qr_verification_token']) ?></code></td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- RA 11261 Statutory Retention Notice if Applicable -->
                            <?php if ($document['is_original_retained'] || $document['service_code'] === 'CERT-FTJ'): ?>
                                <div class="ra11261-box mb-4">
                                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-award-fill text-warning me-2"></i>Republic Act No. 11261 Notice</h6>
                                    <p class="small mb-0">
                                        <strong>First-Time Jobseeker Certification:</strong> Under Section 5 of Republic Act No. 11261, this certification is issued free of charge and the original copy must be presented to and retained by the employer upon commencement of employment.
                                    </p>
                                </div>
                            <?php endif; ?>

                            <!-- Verification Metadata Notice -->
                            <div class="d-flex justify-content-between align-items-center small text-muted border-top pt-3">
                                <div>
                                    <i class="bi bi-clock-history me-1"></i> Verified on: <?= date('F j, Y, g:i A') ?> (PST)
                                </div>
                                <div>
                                    <i class="bi bi-shield-check text-success me-1"></i> Official Digital Registry
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- DOCUMENT NOT FOUND -->
                    <div class="card border-danger border-2 shadow-sm rounded-4 text-center p-4 p-md-5 bg-white">
                        <div class="text-danger mb-3" style="font-size: 3.5rem;">
                            <i class="bi bi-shield-x"></i>
                        </div>
                        <h4 class="fw-bold text-danger mb-2">Record Not Found in Official Registry</h4>
                        <p class="text-muted mb-4 max-w-700 mx-auto">
                            No active barangay clearance or certification matches the query token <code><?= e($tokenQuery) ?></code>.
                            Please double-check the QR code or Document Control Number printed on your physical copy.
                        </p>
                        <div class="alert alert-light border small text-muted max-w-700 mx-auto mb-4">
                            <strong>Note:</strong> Documents are officially recorded only after review and signature by authorized barangay officials. If you recently applied, check your status in the citizen portal.
                        </div>
                        <div>
                            <a href="verify.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Try Another Token
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Quick Sample Token Help Callout -->
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-4 bg-light p-4 text-center">
                    <h6 class="fw-bold text-primary mb-2"><i class="bi bi-info-circle me-1"></i>Sample Verification Tokens for Testing</h6>
                    <p class="small text-muted mb-3">You can click any sample token seeded in the database to test the live verification flow:</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <a href="verify.php?token=QR-FTJ-2026-8F4A9C12" class="btn btn-sm btn-outline-primary">
                            <code>QR-FTJ-2026-8F4A9C12</code> (RA 11261 Sample)
                        </a>
                        <a href="verify.php?token=DOC-FTJ-2026-0001" class="btn btn-sm btn-outline-secondary">
                            <code>DOC-FTJ-2026-0001</code> (Control Number)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
