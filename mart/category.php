<?php
require_once 'includes/header.php';

$catId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT id, name, icon, parent_id FROM categories WHERE id = :id");
$stmt->execute([':id' => $catId]);
$cat = $stmt->fetch();

if (!$cat) {
    echo '<div class="container my-5"><div class="alert alert-warning">Category not found.</div></div>';
    require_once 'includes/footer.php';
    exit;
}

$perPage = 8;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* Include child categories */
$catIds = [$catId];
$sub = $pdo->prepare("SELECT id FROM categories WHERE parent_id = :id");
$sub->execute([':id' => $catId]);
foreach ($sub->fetchAll() as $s) $catIds[] = (int)$s['id'];

$in = implode(',', array_fill(0, count($catIds), '?'));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id IN ($in)");
$stmt->execute($catIds);
$total = (int)$stmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$stmt = $pdo->prepare("
    SELECT id, name, price, old_price, discount, image
    FROM products
    WHERE category_id IN ($in)
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($catIds);
$products = $stmt->fetchAll();
?>

<div class="container my-4">
  <div class="row g-3">

    <div class="col-lg-3">
      <div class="cat-menu">
        <div class="title"><i class="fa fa-th-large me-2"></i> Categories</div>
        <ul>
          <?php foreach ($parents as $p): ?>
            <li>
              <a href="category.php?id=<?= (int)$p['id'] ?>"
                 class="<?= $p['id'] == $catId ? 'active' : '' ?>">
                <span><i class="fa <?= htmlspecialchars($p['icon'] ?: 'fa-tag') ?> me-2"></i><?= htmlspecialchars($p['name']) ?></span>
                <?php if (!empty($children[$p['id']])): ?>
                  <i class="fa fa-chevron-right" style="font-size:.75rem;"></i>
                <?php endif; ?>
              </a>
              <?php if (!empty($children[$p['id']])): ?>
                <div class="submenu">
                  <?php foreach ($children[$p['id']] as $c): ?>
                    <a href="category.php?id=<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="col-lg-9">
      <div class="section-head">
        <h4>
          <i class="fa <?= htmlspecialchars($cat['icon'] ?: 'fa-tag') ?> me-2"></i>
          <?= htmlspecialchars($cat['name']) ?>
          <small class="text-muted" style="font-size:.8rem;">(<?= $total ?> items)</small>
        </h4>
      </div>

      <?php if (!$products): ?>
        <div class="alert alert-info">No products in this category yet.</div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($products as $p): ?>
            <div class="col-6 col-md-4 col-lg-3">
              <div class="p-card">
                <a href="product.php?id=<?= (int)$p['id'] ?>" class="wish"><i class="fa fa-heart"></i></a>
                <a href="product.php?id=<?= (int)$p['id'] ?>">
                  <img src="<?= htmlspecialchars(img($p['image'])) ?>" alt="">
                </a>
                <div class="body">
                  <h6><?= htmlspecialchars($p['name']) ?></h6>
                  <div>
                    <span class="price"><?= money($p['price']) ?></span>
                    <?php if (!empty($p['old_price'])): ?>
                      <span class="old"><?= money($p['old_price']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($p['discount'])): ?>
                      <span class="off"><?= (int)$p['discount'] ?>% off</span>
                    <?php endif; ?>
                  </div>
                  <form action="cart_add.php" method="post" class="mt-2">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="btn btn-sm w-100 btn-add">
                      <i class="fa fa-cart-plus me-1"></i> Add Cart
                    </button>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
          <nav class="mt-4">
            <ul class="pagination justify-content-center">
              <?php for ($i = 1; $i <= $pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                  <a class="page-link" href="?id=<?= $catId ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>