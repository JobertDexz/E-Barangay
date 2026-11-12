<?php
/**
 * Service Card Partial
 * Expected input: $svc (array of service columns)
 */
$isFtJobseeker = ($svc['service_code'] === 'CERT-FTJ');
$categoryBadgeColor = match($svc['category']) {
    'Certifications' => 'bg-primary',
    'Permits' => 'bg-warning text-dark',
    'Legal / Dispute Records' => 'bg-danger',
    default => 'bg-secondary'
};
?>
<div class="col-md-6 col-lg-4 mb-3">
    <div class="service-card <?= $svc['is_confidential'] ? 'confidential-card' : '' ?> h-100 d-flex flex-column justify-content-between">
        <div>
            <!-- Top Badges -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="service-code-pill"><?= e($svc['service_code']) ?></span>
                <span class="badge <?= $categoryBadgeColor ?>"><?= e($svc['category']) ?></span>
            </div>

            <!-- Service Title -->
            <h5 class="fw-bold text-dark mb-2"><?= e($svc['service_name']) ?></h5>
            
            <p class="text-muted small mb-3">
                <?= e($svc['description']) ?>
            </p>

            <!-- Requirements Box -->
            <div class="bg-light p-2 rounded mb-3 small border">
                <strong class="text-secondary d-block mb-1"><i class="bi bi-paperclip me-1"></i>Requirements:</strong>
                <span class="text-dark"><?= e($svc['requirements_text']) ?></span>
            </div>

            <!-- Tags -->
            <div class="d-flex flex-wrap gap-1 mb-3">
                <?php if ($isFtJobseeker): ?>
                    <span class="badge bg-success"><i class="bi bi-award me-1"></i>RA 11261 Fee Waived</span>
                <?php endif; ?>
                <?php if ($svc['requires_interview']): ?>
                    <span class="badge bg-secondary"><i class="bi bi-person-video me-1"></i>Interview</span>
                <?php endif; ?>
                <?php if ($svc['requires_inspection']): ?>
                    <span class="badge bg-secondary"><i class="bi bi-search me-1"></i>Site Inspection</span>
                <?php endif; ?>
                <?php if ($svc['is_confidential']): ?>
                    <span class="badge bg-dark"><i class="bi bi-lock-fill me-1"></i>Confidential</span>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <!-- Meta Bar: Fee, Validity, Processing Time -->
            <div class="d-flex justify-content-between align-items-center border-top pt-2 mb-3 small">
                <div>
                    <span class="text-muted d-block" style="font-size:0.75rem;">OFFICIAL FEE</span>
                    <strong class="fs-6"><?= formatFee($svc['default_fee']) ?></strong>
                </div>
                <div class="text-center">
                    <span class="text-muted d-block" style="font-size:0.75rem;">PROCESSING</span>
                    <span class="fw-semibold text-dark"><?= e($svc['processing_time_label']) ?></span>
                </div>
                <div class="text-end">
                    <span class="text-muted d-block" style="font-size:0.75rem;">VALIDITY</span>
                    <span class="fw-semibold text-dark"><?= $svc['validity_days'] ? e($svc['validity_days']) . ' days' : 'No Expiry' ?></span>
                </div>
            </div>

            <!-- Action Button -->
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php?service_id=<?= (int)$svc['service_id'] ?>#apply" class="btn btn-primary w-100 btn-sm">
                    <i class="bi bi-pencil-square"></i> Apply Online
                </a>
            <?php else: ?>
                <a href="login.php?service_id=<?= (int)$svc['service_id'] ?>" class="btn btn-outline-primary w-100 btn-sm">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In to Apply
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
