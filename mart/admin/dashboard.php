<?php
require_once __DIR__ . '/header.php';

/* Stats */
$stats = [
    'products' => (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'sellers'  => (int)$pdo->query("SELECT COUNT(*) FROM sellers")->fetchColumn(),
    'pending'  => (int)$pdo->query("SELECT COUNT(*) FROM sellers WHERE status='pending'")->fetchColumn(),
    'orders'   => (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
];

$pendingSellers = $pdo->query("
    SELECT id, seller_type, shop_name, owner_name, created_at
    FROM sellers WHERE status='pending'
    ORDER BY created_at DESC LIMIT 5
")->fetchAll();

$recentOrders = $pdo->query("
    SELECT id, customer_name, total, status, created_at
    FROM orders ORDER BY created_at DESC LIMIT 5
")->fetchAll();
?>

<h3 class="admin-page-title">Dashboard</h3>
<p class="admin-page-sub">Welcome back, <?= htmlspecialchars($adminName) ?></p>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <a href="products.php" class="admin-stat">
      <i class="fa fa-box"></i>
      <div class="admin-stat-num"><?= $stats['products'] ?></div>
      <div class="admin-stat-label">Products</div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="sellers.php?status=active" class="admin-stat">
      <i class="fa fa-store"></i>
      <div class="admin-stat-num"><?= $stats['sellers'] ?></div>
      <div class="admin-stat-label">Sellers</div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="sellers.php?status=pending" class="admin-stat <?= $stats['pending'] ? 'highlight' : '' ?>">
      <i class="fa fa-hourglass-half"></i>
      <div class="admin-stat-num"><?= $stats['pending'] ?></div>
      <div class="admin-stat-label">Pending</div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="orders.php" class="admin-stat">
      <i class="fa fa-shopping-bag"></i>
      <div class="admin-stat-num"><?= $stats['orders'] ?></div>
      <div class="admin-stat-label">Orders</div>
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="admin-card">
      <div class="admin-card-head">
        <h6><i class="fa fa-hourglass-half me-1"></i> Pending Sellers</h6>
        <a href="sellers.php?status=pending">View all</a>
      </div>
      <?php if (!$pendingSellers): ?>
        <div class="text-center text-muted small py-4">No pending applications.</div>
      <?php else: ?>
        <?php foreach ($pendingSellers as $s): ?>
          <div class="admin-row">
            <div>
              <div class="fw-semibold"><?= htmlspecialchars($s['shop_name'] ?: $s['owner_name']) ?></div>
              <div class="small text-muted">
                <?= $s['seller_type'] === 'business' ? 'Business' : 'Individual' ?>
                · <?= htmlspecialchars($s['owner_name']) ?>
                · <?= date('d M Y', strtotime($s['created_at'])) ?>
              </div>
            </div>
            <a href="sellers.php?status=pending#seller-<?= (int)$s['id'] ?>"
               class="btn btn-sm btn-outline-primary">Review</a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="admin-card">
      <div class="admin-card-head">
        <h6><i class="fa fa-shopping-bag me-1"></i> Recent Orders</h6>
        <a href="orders.php">View all</a>
      </div>
      <?php if (!$recentOrders): ?>
        <div class="text-center text-muted small py-4">No orders yet.</div>
      <?php else: ?>
        <?php foreach ($recentOrders as $o): ?>
          <div class="admin-row">
            <div>
              <div class="fw-semibold">#<?= (int)$o['id'] ?> · <?= htmlspecialchars($o['customer_name']) ?></div>
              <div class="small text-muted">
                <?= date('d M Y, H:i', strtotime($o['created_at'])) ?>
                · <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($o['status']) ?></span>
              </div>
            </div>
            <div class="fw-bold" style="color:var(--admin);">
              Rs. <?= number_format((float)$o['total'], 0) ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>