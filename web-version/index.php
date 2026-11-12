<?php
/**
 * e-Barangay Web Portal - Public Landing Page
 * Barangay Santa Veronica District, Guimba, Nueva Ecija
 */

require_once __DIR__ . '/includes/db.php';
$pdo = getDb();
$profile = getBarangayProfile($pdo);
$services = getActiveServices($pdo);

$pageTitle = "Official Citizen Web Portal";
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner (Mobile First) -->
<section class="hero-banner">
    <div class="container position-relative" style="z-index: 2;">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="hero-badge mb-3">
                    <i class="bi bi-patch-check-fill"></i> Republic of the Philippines &bull; LGU Guimba
                </div>
                <h1 class="display-5 fw-bold text-white mb-2">
                    <?= e($profile['barangay_name']) ?>
                </h1>
                <p class="lead text-info mb-3">
                    <?= e($profile['municipality_city']) ?>, <?= e($profile['province']) ?> &bull; <?= e($profile['region']) ?>
                </p>
                <p class="text-white-50 mb-4 fs-6">
                    Fast, transparent, and accessible public service. Apply online for barangay clearances, certifications, and permits; upload documentary requirements; monitor real-time queue status; and verify document authenticity anytime.
                </p>

                <div class="d-flex flex-wrap gap-2">
                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php" class="btn btn-gold btn-lg text-dark shadow">
                            <i class="bi bi-speedometer2"></i> Open Citizen Dashboard
                        </a>
                        <a href="dashboard.php#apply" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-file-earmark-plus"></i> Apply for Document
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-gold btn-lg text-dark shadow">
                            <i class="bi bi-person-plus-fill"></i> Register Citizen Account
                        </a>
                        <a href="login.php" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Citizen Sign In
                        </a>
                    <?php endif; ?>
                    <a href="verify.php" class="btn btn-outline-info btn-lg">
                        <i class="bi bi-qr-code-scan"></i> Verify Document QR
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <!-- Quick Verification Widget -->
                <div class="card border-0 shadow-lg text-dark p-3 p-md-4 rounded-4" style="background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bg-primary text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                            <i class="bi bi-shield-lock-fill fs-4"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-primary">Instant QR Verification</h5>
                            <small class="text-muted">Validate issued clearances & permits</small>
                        </div>
                    </div>
                    <form action="verify.php" method="GET" class="mb-2">
                        <div class="input-group mb-2">
                            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                            <input type="text" name="token" class="form-control" placeholder="Enter QR Token or Control No." required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-circle-fill"></i> Verify Authenticity
                        </button>
                    </form>
                    <small class="text-muted d-block text-center mt-2">
                        <i class="bi bi-shield-check text-success"></i> Privacy Protected: Never exposes sensitive personal data.
                    </small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Citizen Quick Stats / Highlights Bar -->
<section class="py-3 bg-white border-bottom shadow-sm">
    <div class="container">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-files fs-3 text-primary"></i>
                    <div class="text-start">
                        <div class="fw-bold fs-5 text-dark">14 Services</div>
                        <small class="text-muted">Certifications & Permits</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-award-fill fs-3 text-warning"></i>
                    <div class="text-start">
                        <div class="fw-bold fs-5 text-dark">RA 11261</div>
                        <small class="text-muted">Free First-Time Jobseeker</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-ticket-perforated-fill fs-3 text-danger"></i>
                    <div class="text-start">
                        <div class="fw-bold fs-5 text-dark">Live Queue</div>
                        <small class="text-muted">Real-Time "Now Serving"</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-cloud-arrow-up-fill fs-3 text-success"></i>
                    <div class="text-start">
                        <div class="fw-bold fs-5 text-dark">Online Intake</div>
                        <small class="text-muted">Upload Requirements</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- RA 11261 Spotlight Callout -->
<section class="container mt-4">
    <div class="ra11261-box shadow-sm">
        <div class="row align-items-center">
            <div class="col-md-9">
                <h5 class="fw-bold mb-1"><i class="bi bi-info-circle-fill me-2"></i>First-Time Jobseekers Assistance Act (Republic Act No. 11261)</h5>
                <p class="mb-0 small">
                    First-time Filipino jobseekers residing in Barangay Santa Veronica District for at least six (6) months are entitled to free government-issued documents, including the <strong>First-Time Jobseeker Certification (CERT-FTJ)</strong>, valid for one (1) year. Avail of this benefit online via our self-service portal.
                </p>
            </div>
            <div class="col-md-3 text-md-end mt-3 mt-md-0">
                <a href="<?= isLoggedIn() ? 'dashboard.php?service=CERT-FTJ#apply' : 'register.php' ?>" class="btn btn-warning btn-sm fw-bold">
                    <i class="bi bi-arrow-right-circle-fill"></i> Avail Free Certificate
                </a>
            </div>
        </div>
    </div>
</section>

<!-- 14-Service Catalog Section -->
<section id="services" class="container my-5">
    <div class="text-center mb-4">
        <span class="badge bg-primary text-uppercase px-3 py-2 mb-2">Service Catalog</span>
        <h2 class="fw-bold text-dark">Official Barangay Services</h2>
        <p class="text-muted max-w-700 mx-auto">
            Browse through all 14 official certifications, clearances, and dispute records available at Barangay Santa Veronica District. Apply online to bypass paper lines.
        </p>
    </div>

    <!-- Category Filter Tabs -->
    <ul class="nav nav-pills justify-content-center mb-4 gap-2" id="serviceTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill px-3 py-2 fw-semibold" id="tab-all" data-bs-toggle="pill" data-bs-target="#pills-all" type="button" role="tab">
                <i class="bi bi-collection me-1"></i> All Services (<?= count($services) ?>)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="tab-cert" data-bs-toggle="pill" data-bs-target="#pills-cert" type="button" role="tab">
                <i class="bi bi-patch-check me-1"></i> Certifications
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="tab-perm" data-bs-toggle="pill" data-bs-target="#pills-perm" type="button" role="tab">
                <i class="bi bi-briefcase me-1"></i> Permits & Clearances
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-3 py-2 fw-semibold" id="tab-legal" data-bs-toggle="pill" data-bs-target="#pills-legal" type="button" role="tab">
                <i class="bi bi-shield-shaded me-1"></i> Legal & Dispute Records
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="serviceTabsContent">
        <!-- ALL SERVICES -->
        <div class="tab-pane fade show active" id="pills-all" role="tabpanel">
            <div class="row g-3">
                <?php foreach ($services as $svc): ?>
                    <?php include __DIR__ . '/includes/service_card_partial.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CERTIFICATIONS -->
        <div class="tab-pane fade" id="pills-cert" role="tabpanel">
            <div class="row g-3">
                <?php foreach ($services as $svc): ?>
                    <?php if ($svc['category'] === 'Certifications'): ?>
                        <?php include __DIR__ . '/includes/service_card_partial.php'; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PERMITS -->
        <div class="tab-pane fade" id="pills-perm" role="tabpanel">
            <div class="row g-3">
                <?php foreach ($services as $svc): ?>
                    <?php if ($svc['category'] === 'Permits'): ?>
                        <?php include __DIR__ . '/includes/service_card_partial.php'; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- LEGAL / DISPUTE -->
        <div class="tab-pane fade" id="pills-legal" role="tabpanel">
            <div class="row g-3">
                <?php foreach ($services as $svc): ?>
                    <?php if ($svc['category'] === 'Legal / Dispute Records'): ?>
                        <?php include __DIR__ . '/includes/service_card_partial.php'; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- How It Works (Citizen Workflow) -->
<section class="py-5 bg-white border-top border-bottom">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-secondary text-uppercase px-3 py-2 mb-2">Step-by-Step Guide</span>
            <h3 class="fw-bold">How the Online Portal Works</h3>
            <p class="text-muted">Effortless document request and queue management for every Santa Veronica resident</p>
        </div>

        <div class="row g-4 text-center">
            <div class="col-md-3">
                <div class="card h-100 border-0 p-3 shadow-sm rounded-4">
                    <div class="mx-auto mb-3 text-primary bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi bi-person-check fs-2"></i>
                    </div>
                    <h5 class="fw-bold">1. Register / Sign In</h5>
                    <p class="text-muted small mb-0">Create your verified resident profile with contact and address details.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 p-3 shadow-sm rounded-4">
                    <div class="mx-auto mb-3 text-info bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi bi-file-earmark-arrow-up fs-2"></i>
                    </div>
                    <h5 class="fw-bold">2. Apply & Upload</h5>
                    <p class="text-muted small mb-0">Select from the 14 services and submit your ID and documentary requirements.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 p-3 shadow-sm rounded-4">
                    <div class="mx-auto mb-3 text-warning bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi bi-ticket-detailed fs-2"></i>
                    </div>
                    <h5 class="fw-bold">3. Auto Queue Ticket</h5>
                    <p class="text-muted small mb-0">Instantly receive your live queue ticket (P-XXX priority or R-XXX regular) and track your place in line.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 border-0 p-3 shadow-sm rounded-4">
                    <div class="mx-auto mb-3 text-success bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi bi-qr-code fs-2"></i>
                    </div>
                    <h5 class="fw-bold">4. Claim & Verify</h5>
                    <p class="text-muted small mb-0">Claim your signed document with tamper-proof QR code verifiable by employers and agencies.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Barangay Leadership & Hall Information -->
<section class="container my-5">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="row g-0">
            <div class="col-lg-6 bg-primary text-white p-4 p-md-5 d-flex flex-column justify-content-between">
                <div>
                    <span class="badge bg-warning text-dark text-uppercase px-3 py-2 mb-3">Official Directory</span>
                    <h3 class="fw-bold mb-3"><?= e($profile['barangay_name']) ?></h3>
                    <p class="text-white-50 mb-4">
                        Committed to delivering honest, responsive, and technology-driven governance to all families in Santa Veronica District.
                    </p>

                    <div class="mb-3">
                        <strong class="d-block text-warning small text-uppercase">Punong Barangay</strong>
                        <div class="fs-5 fw-bold"><?= e($profile['punong_barangay_name']) ?></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <strong class="d-block text-warning small text-uppercase">Barangay Secretary</strong>
                            <div class="fw-semibold"><?= e($profile['secretary_name']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <strong class="d-block text-warning small text-uppercase">Barangay Treasurer</strong>
                            <div class="fw-semibold"><?= e($profile['treasurer_name']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top border-white-50 small text-white-50">
                    <i class="bi bi-building me-1"></i> Guimba, Nueva Ecija &bull; Region III (Central Luzon)
                </div>
            </div>

            <div class="col-lg-6 p-4 p-md-5 bg-light d-flex flex-column justify-content-between">
                <div>
                    <h4 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history text-primary me-2"></i>Barangay Hall Office Hours</h4>
                    <p class="text-muted mb-4">
                        For physical document claiming, personal interviews, or cash fee payment, visit our hall during regular office hours:
                    </p>

                    <div class="bg-white p-3 rounded-3 shadow-sm mb-3 border">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-calendar3 fs-3 text-primary"></i>
                            <div>
                                <div class="fw-bold text-dark"><?= e($profile['office_hours']) ?></div>
                                <small class="text-muted">Standard working days (Except Philippine Regular Holidays)</small>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-3 rounded-3 shadow-sm mb-3 border">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-geo-alt fs-3 text-danger"></i>
                            <div>
                                <div class="fw-bold text-dark">Barangay Hall Location</div>
                                <small class="text-muted"><?= e($profile['hall_address']) ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="tel:<?= e($profile['contact_number']) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-telephone-fill"></i> Call <?= e($profile['contact_number'] ?: 'Barangay Hall') ?>
                    </a>
                    <a href="mailto:<?= e($profile['email']) ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-envelope-fill"></i> Email Secretary
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
