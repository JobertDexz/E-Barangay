<?php
if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
    $pdo = getDb();
}
$profile = getBarangayProfile($pdo);
$currentUser = currentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#0b3954">
    <meta name="description" content="Official Citizen Portal of Barangay Santa Veronica District, Guimba, Nueva Ecija. Apply for clearances, certifications, track applications and live queue tickets.">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' : '' ?><?= e($profile['barangay_name']) ?>, <?= e($profile['municipality_city']) ?></title>
    
    <!-- Bootstrap 5.3.3 CSS (CDN + Offline fallback capability) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom Mobile-First Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Official Top Bar -->
    <div class="official-topbar">
        <div class="container d-flex flex-wrap justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-geo-alt-fill text-warning"></i>
                <span><?= e($profile['hall_address']) ?></span>
            </div>
            <div class="d-none d-md-flex align-items-center gap-3">
                <span><i class="bi bi-clock-fill text-info me-1"></i><?= e($profile['office_hours']) ?></span>
                <span><i class="bi bi-telephone-fill text-success me-1"></i><?= e($profile['contact_number'] ?: 'Hotline Available') ?></span>
            </div>
        </div>
    </div>

    <!-- Main Navigation Bar -->
    <nav class="navbar navbar-expand-lg official-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <div class="navbar-brand-seal">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div>
                    <div class="brand-text-main"><?= e($profile['barangay_name']) ?></div>
                    <div class="brand-text-sub"><?= e($profile['municipality_city']) ?>, <?= e($profile['province']) ?> &bull; e-Portal</div>
                </div>
            </a>

            <button class="navbar-toggler text-white border-white-50" type="button" data-bs-toggle="collapse" data-bs-target="#officialNav" aria-controls="officialNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list fs-2"></i>
            </button>

            <div class="collapse navbar-collapse" id="officialNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 py-2 py-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">
                            <i class="bi bi-house-door me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php#services">
                            <i class="bi bi-grid me-1"></i> Services (14)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'verify.php' ? 'active' : '' ?>" href="verify.php">
                            <i class="bi bi-qr-code-scan me-1 text-warning"></i> QR Verify
                        </a>
                    </li>

                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                                <i class="bi bi-speedometer2 me-1 text-info"></i> Citizen Dashboard
                            </a>
                        </li>
                        <li class="nav-item dropdown ms-lg-2">
                            <a class="btn btn-outline-light btn-sm dropdown-toggle w-100 text-start" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i> <?= e($currentUser['full_name'] ?? 'Citizen') ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userMenu">
                                <li class="dropdown-header">
                                    <strong><?= e($currentUser['username'] ?? '') ?></strong><br>
                                    <small class="text-muted"><?= e($currentUser['role'] ?? 'Citizen') ?> &bull; <?= e($_SESSION['resident_code'] ?? 'Registered') ?></small>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-file-earmark-text me-2"></i>My Applications</a></li>
                                <li><a class="dropdown-item" href="dashboard.php#queue"><i class="bi bi-ticket-perforated me-2"></i>Live Queue Status</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                            <a class="btn btn-outline-light btn-sm w-100" href="login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                            </a>
                        </li>
                        <li class="nav-item ms-lg-1 mt-2 mt-lg-0">
                            <a class="btn btn-gold btn-sm w-100 text-dark" href="register.php">
                                <i class="bi bi-person-plus-fill me-1"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Notifications -->
    <?php if ($flash): ?>
        <div class="container mt-3">
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm d-flex align-items-center gap-2" role="alert">
                <i class="bi <?= $flash['type'] === 'success' ? 'bi-check-circle-fill' : ($flash['type'] === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill') ?> fs-5"></i>
                <div class="flex-grow-1"><?= e($flash['message']) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <main class="py-4">
