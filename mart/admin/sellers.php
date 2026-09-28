<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

/* Simple admin gate — change to a real check later */
$ADMIN_PASS = 'admin123';
$isAdmin = !empty($_SESSION['is_admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    if ($_POST['admin_pass'] === $ADMIN_PASS) {
        $_SESSION['is_admin'] = true;
        header('Location: sellers.php');
        exit;
    } else {
        $loginError = 'Wrong password.';
    }
}

if (!$isAdmin) {
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="container my-5" style="max-width:400px;">
      <div class="bg-white p-4 rounded shadow-sm">
        <h5 style="color:var(--brand);font-weight:800;">Admin Access</h5>
        <?php if (!empty($loginError)): ?>
          <div class="alert alert-danger py-2 small"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="post">
          <input type="password" name="admin_pass" class="form-control mb-3" placeholder="Admin password" required>
          <button class="btn w-100 text-white" style="background:var(--brand);">Enter</button>
        </form>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

/* ---------- Approve / Reject action ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['seller_id'])) {
    $action = $_POST['action'];
    $sid    = (int)$_POST['seller_id'];
    $note   = trim($_POST['admin_note'] ?? '');

    if ($action === 'approve') {
        $q = $pdo->prepare("UPDATE sellers SET status='active', admin_note=:n, approved_at=NOW() WHERE id=:id");
        $q->execute([':n' => $note ?: null, ':id' => $sid]);
    } elseif ($action === 'reject') {
        $q = $pdo->prepare("UPDATE sellers SET status='rejected', admin_note=:n WHERE id=:id");
        $q->execute([':n' => $note ?: 'Rejected by admin', ':id' => $sid]);
    } elseif ($action === 'pending') {
        $q = $pdo->prepare("UPDATE sellers SET status='pending', admin_note=NULL, approved_at=NULL WHERE id=:id");
        $q->execute([':id' => $sid]);
    }
    header('Location: sellers.php');
    exit;
}

/* ---------- Filter ---------- */
$filter = $_GET['status'] ?? 'pending';
$where = '';
$params = [];
if (in_array($filter, ['pending','active','rejected'], true)) {
    $where = "WHERE status = :s";
    $params[':s'] = $filter;
}

$stmt = $pdo->prepare("SELECT * FROM sellers $where ORDER BY created_at DESC");
$stmt->execute($params);
$sellers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">

  <div class="section-head">
    <h4><i class="fa fa-user-check me-2"></i> Seller Applications</h4>
    <a href="?status=<?= $filter === 'pending' ? 'active' : 'pending' ?>">
      Switch to <?= $filter === 'pending' ? 'Active' : 'Pending' ?>
    </a>
  </div>

  <div class="mb-3">
    <a href="?status=pending"  class="chip <?= $filter==='pending'?'active':'' ?>">Pending</a>
    <a href="?status=active"   class="chip <?= $filter==='active'?'active':'' ?>">Active</a>
    <a href="?status=rejected" class="chip <?= $filter==='rejected'?'active':'' ?>">Rejected</a>
    <a href="?status=all"      class="chip <?= $filter==='all'?'active':'' ?>">All</a>
  </div>

  <?php if (!$sellers): ?>
    <div class="alert alert-info">No sellers in this category.</div>
  <?php else: ?>
    <?php foreach ($sellers as $s): ?>
      <div class="seller-review-card">
        <div class="row g-3">

          <div class="col-md-8">
            <div class="d-flex align-items-center gap-2 mb-2">
              <span class="badge bg-<?=
                $s['status'] === 'active'  ? 'success' :
                ($s['status'] === 'rejected' ? 'danger' : 'warning')
              ?> text-<?= $s['status'] === 'pending' ? 'dark' : 'white' ?>">
                <?= strtoupper($s['status']) ?>
              </span>
              <span class="badge bg-light text-dark">
                <?= $s['seller_type'] === 'business' ? 'Business' : 'Individual' ?>
              </span>
            </div>

            <h5 class="mb-1" style="color:var(--brand);font-weight:800;">
              <?= htmlspecialchars($s['shop_name']) ?>
            </h5>
            <div class="small text-muted mb-2">
              <i class="fa fa-user me-1"></i> <?= htmlspecialchars($s['owner_name']) ?>
              · <i class="fa fa-phone me-1"></i> <?= htmlspecialchars($s['owner_phone']) ?>
              · <i class="fa fa-envelope me-1"></i> <?= htmlspecialchars($s['owner_email']) ?>
            </div>

            <div class="small"><strong>Shop address:</strong> <?= htmlspecialchars($s['shop_address']) ?></div>
            <div class="small"><strong>Owner address:</strong> <?= htmlspecialchars($s['owner_address']) ?></div>

            <?php if ($s['seller_type'] === 'business'): ?>
              <div class="small mt-1">
                <strong>PAN:</strong> <?= htmlspecialchars($s['pan_number'] ?? '—') ?>
                · <strong>VAT:</strong> <?= htmlspecialchars($s['vat_number'] ?? '—') ?>
              </div>
            <?php else: ?>
              <div class="small mt-1">
                <strong>Citizenship No:</strong> <?= htmlspecialchars($s['citizenship_no'] ?? '—') ?>
              </div>
            <?php endif; ?>

            <div class="mt-2">
              <?php if (!empty($s['pan_cert_file'])): ?>
                <a href="../<?= htmlspecialchars($s['pan_cert_file']) ?>" target="_blank"
                   class="btn btn-sm btn-outline-secondary">
                  <i class="fa fa-file me-1"></i> PAN Certificate
                </a>
              <?php endif; ?>
              <?php if (!empty($s['cit_front_file'])): ?>
                <a href="../<?= htmlspecialchars($s['cit_front_file']) ?>" target="_blank"
                   class="btn btn-sm btn-outline-secondary ms-2">
                  <i class="fa fa-id-card me-1"></i> Citizenship Front
                </a>
              <?php endif; ?>
              <?php if (!empty($s['cit_back_file'])): ?>
                <a href="../<?= htmlspecialchars($s['cit_back_file']) ?>" target="_blank"
                   class="btn btn-sm btn-outline-secondary ms-2">
                  <i class="fa fa-id-card me-1"></i> Citizenship Back
                </a>
              <?php endif; ?>
            </div>

            <?php if (!empty($s['admin_note'])): ?>
              <div class="alert alert-light border small mt-2 mb-0">
                <strong>Admin note:</strong> <?= htmlspecialchars($s['admin_note']) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="col-md-4">
            <?php if ($s['status'] === 'pending'): ?>
              <form method="post" class="mb-2">
                <input type="hidden" name="seller_id" value="<?= (int)$s['id'] ?>">
                <textarea name="admin_note" class="form-control form-control-sm mb-2"
                          rows="2" placeholder="Optional note…"></textarea>
                <button name="action" value="approve" class="btn btn-sm btn-success w-100 mb-1">
                  <i class="fa fa-check me-1"></i> Approve
                </button>
                <button name="action" value="reject" class="btn btn-sm btn-outline-danger w-100">
                  <i class="fa fa-times me-1"></i> Reject
                </button>
              </form>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="seller_id" value="<?= (int)$s['id'] ?>">
                <button name="action" value="pending" class="btn btn-sm btn-outline-secondary w-100">
                  <i class="fa fa-undo me-1"></i> Move to Pending
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<style>
.seller-review-card{
  background:#fff;border-radius:10px;padding:18px;
  box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:14px;
}
.chip{
  display:inline-block;padding:6px 14px;border-radius:20px;
  background:#fff;color:#555;text-decoration:none;font-size:.85rem;
  font-weight:600;box-shadow:0 2px 6px rgba(0,0,0,.05);margin-right:6px;
}
.chip:hover{color:var(--brand);}
.chip.active{background:var(--brand);color:#fff;}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>