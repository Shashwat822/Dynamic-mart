<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5" style="max-width:600px;">
  <div class="bg-white rounded shadow-sm p-5 text-center">

    <div style="width:80px;height:80px;border-radius:50%;background:#fff7e0;
                color:#d4a418;display:inline-flex;align-items:center;
                justify-content:center;font-size:2rem;">
      <i class="fa fa-hourglass-half"></i>
    </div>

    <h3 class="mt-3 mb-2" style="color:var(--brand);font-weight:800;">
      Application Received
    </h3>

    <p class="text-muted">
      Thank you for applying to sell on <strong>Dynamic Mart</strong>.
      Your seller account is <strong>under review</strong>.
    </p>

    <div class="alert alert-warning text-start small mt-4">
      <strong><i class="fa fa-info-circle me-1"></i> What happens next?</strong>
      <ul class="mb-0 mt-2">
        <li>Our team verifies your documents (usually within 24–48 hours).</li>
        <li>You'll receive an email once your account is approved.</li>
        <li>You can then log in with the same email/password and start listing products.</li>
      </ul>
    </div>

    <p class="small text-muted mt-3 mb-3">
      Registered email: <strong><?= htmlspecialchars($_SESSION['seller_pending_email'] ?? '—') ?></strong>
    </p>

    <a href="../index.php" class="btn text-white" style="background:var(--brand);">
      <i class="fa fa-home me-1"></i> Back to Home
    </a>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>