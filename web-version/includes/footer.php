    </main>

    <!-- Official Barangay Footer -->
    <footer class="bg-dark text-white pt-5 pb-3 border-top border-warning border-3 mt-auto">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="navbar-brand-seal bg-light text-primary" style="width: 38px; height: 38px; font-size: 1.1rem;">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <h5 class="text-white mb-0"><?= e($profile['barangay_name']) ?></h5>
                            <small class="text-warning"><?= e($profile['municipality_city']) ?>, <?= e($profile['province']) ?></small>
                        </div>
                    </div>
                    <p class="text-white-50 small mb-2">
                        Official digital document processing, online application intake, and queue ticketing portal serving the constituents of Santa Veronica District, Guimba, Nueva Ecija.
                    </p>
                    <p class="text-white-50 small mb-0">
                        <i class="bi bi-geo-alt-fill text-warning me-1"></i> <?= e($profile['hall_address']) ?>
                    </p>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h6 class="text-uppercase text-warning fw-bold mb-3">Barangay Officials</h6>
                    <ul class="list-unstyled text-white-50 small mb-0">
                        <li class="mb-2"><strong class="text-white"><?= e($profile['punong_barangay_name']) ?></strong><br>Punong Barangay</li>
                        <li class="mb-2"><strong class="text-white"><?= e($profile['secretary_name']) ?></strong><br>Barangay Secretary</li>
                        <li class="mb-2"><strong class="text-white"><?= e($profile['treasurer_name']) ?></strong><br>Barangay Treasurer</li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-6">
                    <h6 class="text-uppercase text-warning fw-bold mb-3">Citizen Links</h6>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-2"><a href="index.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-chevron-right small me-1"></i>Home Portal</a></li>
                        <li class="mb-2"><a href="index.php#services" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-chevron-right small me-1"></i>14 Services</a></li>
                        <li class="mb-2"><a href="verify.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-chevron-right small me-1"></i>QR Verification</a></li>
                        <li class="mb-2"><a href="dashboard.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-chevron-right small me-1"></i>Self-Service</a></li>
                        <li class="mb-2"><a href="login.php" class="text-white-50 text-decoration-none hover-white"><i class="bi bi-chevron-right small me-1"></i>Citizen Sign In</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h6 class="text-uppercase text-warning fw-bold mb-3">Emergency & Contact</h6>
                    <div class="bg-secondary bg-opacity-25 p-3 rounded small text-white-50 mb-3">
                        <div class="mb-1"><strong class="text-white">Office Hours:</strong> <?= e($profile['office_hours']) ?></div>
                        <div class="mb-1"><strong class="text-white">Hall Phone:</strong> <?= e($profile['contact_number'] ?: '(044) 000-0000') ?></div>
                        <div class="mb-1"><strong class="text-white">Official Email:</strong> <?= e($profile['email'] ?: 'contact@ebarangay.local') ?></div>
                        <div><strong class="text-white">Guimba PNP Hotline:</strong> 0998-598-5380</div>
                    </div>
                    <span class="badge bg-success"><i class="bi bi-check-circle-fill me-1"></i>RA 11261 Compliant</span>
                    <span class="badge bg-primary"><i class="bi bi-clock-history me-1"></i>ARTA Standards</span>
                </div>
            </div>

            <hr class="border-secondary my-4">

            <div class="d-flex flex-wrap justify-content-between align-items-center small text-white-50">
                <div>
                    &copy; <?= date('Y') ?> <?= e($profile['barangay_name']) ?>, <?= e($profile['municipality_city']) ?>, <?= e($profile['province']) ?>. All Rights Reserved.
                </div>
                <div class="mt-2 mt-md-0">
                    Shared Database Interoperable &bull; Web & WinForms Unified
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper (Local with CDN fallback) -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        if (typeof bootstrap === 'undefined') {
            document.write('<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"><\/script>');
        }
    </script>
    <!-- Custom Web Portal Script -->
    <script src="assets/js/app.js"></script>
</body>
</html>
