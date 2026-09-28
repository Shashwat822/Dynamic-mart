<?php
require_once 'includes/header.php';

$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$sort    = $_GET['sort']    ?? 'new';
$catId   = isset($_GET['cat']) && $_GET['cat'] !== '' ? (int)$_GET['cat'] : 0;
$minP    = isset($_GET['min']) && $_GET['min'] !== '' ? (float)$_GET['min'] : null;
$maxP    = isset($_GET['max']) && $_GET['max'] !== '' ? (float)$_GET['max'] : null;
$view    = $_GET['view']    ?? 'grid';   // grid | list

/* ---------- Build WHERE clause ---------- */
$where  = [];
$params = [];

if ($catId) {
    /* include child categories too */
    $catIds = [$catId];
    $sub = $pdo->prepare("SELECT id FROM categories WHERE parent_id = :id");
    $sub->execute([':id' => $catId]);
    foreach ($sub->fetchAll() as $s) $catIds[] = (int)$s['id'];

    $in = implode(',', array_fill(0, count($catIds), '?'));
    $where[] = "category_id IN ($in)";
    foreach ($catIds as $cid) $params[] = $cid;
}
if ($minP !== null) { $where[] = "price >= ?"; $params[] = $minP; }
if ($maxP !== null) { $where[] = "price <= ?"; $params[] = $maxP; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ---------- Sort ---------- */
$orderBy = "created_at DESC";
if ($sort === 'low')  $orderBy = "price ASC";
if ($sort === 'high') $orderBy = "price DESC";
if ($sort === 'name') $orderBy = "name ASC";
if ($sort === 'disc') $orderBy = "IFNULL(discount,0) DESC";

/* ---------- Count + fetch ---------- */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, ceil($total / $perPage));

$fetchStmt = $pdo->prepare("
    SELECT p.id, p.name, p.description, p.price, p.old_price, p.discount, p.image,
           c.name AS cat_name, c.icon AS cat_icon
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    $whereSql
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
");
$fetchStmt->execute($params);
$products = $fetchStmt->fetchAll();

/* ---------- Current category name (for breadcrumb) ---------- */
$currentCat = null;
if ($catId) {
    $cs = $pdo->prepare("SELECT id, name, icon FROM categories WHERE id = :id");
    $cs->execute([':id' => $catId]);
    $currentCat = $cs->fetch();
}

/* ---------- Category chips ---------- */
$chipCats = $pdo->query("
    SELECT c.id, c.name, c.icon,
           (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS cnt
    FROM categories c
    WHERE c.parent_id IS NULL
    ORDER BY cnt DESC
    LIMIT 8
")->fetchAll();
?>

<div class="container my-4">

  <!-- ============ HERO HEADER ============ -->
  <div class="listing-hero mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h3 class="mb-1">
          <?php if ($currentCat): ?>
            <i class="fa <?= htmlspecialchars($currentCat['icon'] ?: 'fa-tag') ?> me-2"></i>
            <?= htmlspecialchars($currentCat['name']) ?>
          <?php else: ?>
            <i class="fa fa-th me-2"></i> All Products
          <?php endif; ?>
        </h3>
        <div class="opacity-75 small">
          <?= $total ?> product<?= $total === 1 ? '' : 's' ?>
          <?php if ($minP !== null || $maxP !== null): ?>
            · Rs. <?= $minP !== null ? (int)$minP : '0' ?> – <?= $maxP !== null ? (int)$maxP : '∞' ?>
          <?php endif; ?>
        </div>
      </div>

      <form method="get" class="listing-search">
        <input type="hidden" name="cat" value="<?= $catId ?: '' ?>">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
        <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
        <i class="fa fa-search"></i>
        <input type="text" name="q" placeholder="Filter by name..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
      </form>
    </div>
  </div>

  <!-- ============ CATEGORY CHIPS ============ -->
  <div class="chip-row mb-3">
    <a href="products.php<?= $sort !== 'new' ? '?sort='.$sort : '' ?>"
       class="chip <?= !$catId ? 'active' : '' ?>">
      <i class="fa fa-th"></i> All
    </a>
    <?php foreach ($chipCats as $c): ?>
      <a href="?cat=<?= (int)$c['id'] ?>&sort=<?= htmlspecialchars($sort) ?><?= $view==='list'?'&view=list':'' ?>"
         class="chip <?= $catId == $c['id'] ? 'active' : '' ?>">
        <i class="fa <?= htmlspecialchars($c['icon'] ?: 'fa-tag') ?>"></i>
        <?= htmlspecialchars($c['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ============ TOOLBAR: SORT + PRICE + VIEW ============ -->
  <div class="listing-toolbar mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">

      <span class="small text-muted d-none d-md-inline">
        <i class="fa fa-sort me-1"></i> Sort:
      </span>
      <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'new','page'=>1])) ?>"
         class="sort-pill <?= $sort==='new'?'active':'' ?>">Newest</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'low','page'=>1])) ?>"
         class="sort-pill <?= $sort==='low'?'active':'' ?>">Price ↑</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'high','page'=>1])) ?>"
         class="sort-pill <?= $sort==='high'?'active':'' ?>">Price ↓</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'disc','page'=>1])) ?>"
         class="sort-pill <?= $sort==='disc'?'active':'' ?>">Top Discount</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'name','page'=>1])) ?>"
         class="sort-pill <?= $sort==='name'?'active':'' ?>">A-Z</a>
    </div>

    <div class="view-toggle">
      <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'grid'])) ?>"
         class="vt-btn <?= $view==='grid'?'active':'' ?>" title="Grid view">
        <i class="fa fa-th-large"></i>
      </a>
      <a href="?<?= http_build_query(array_merge($_GET, ['view'=>'list'])) ?>"
         class="vt-btn <?= $view==='list'?'active':'' ?>" title="List view">
        <i class="fa fa-list"></i>
      </a>
    </div>
  </div>

  <!-- ============ PRICE FILTER ============ -->
  <form method="get" class="price-filter mb-3">
    <?php foreach ($_GET as $k => $v): if (in_array($k, ['min','max','page'])) continue; ?>
      <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
    <?php endforeach; ?>

    <span class="small text-muted"><i class="fa fa-tag me-1"></i> Price range:</span>
    <input type="number" name="min" placeholder="Min" min="0"
           value="<?= $minP !== null ? (int)$minP : '' ?>">
    <span class="text-muted">–</span>
    <input type="number" name="max" placeholder="Max" min="0"
           value="<?= $maxP !== null ? (int)$maxP : '' ?>">
    <button class="btn btn-sm text-white" style="background:var(--brand);">Apply</button>
    <?php if ($minP !== null || $maxP !== null): ?>
      <a href="products.php" class="btn btn-sm btn-outline-secondary">Clear</a>
    <?php endif; ?>
  </form>

  <!-- ============ PRODUCTS ============ -->
  <?php if (!$products): ?>
    <div class="empty-state">
      <i class="fa fa-box-open"></i>
      <h5>No products found</h5>
      <p class="text-muted mb-3">Try a different category or price range.</p>
      <a href="products.php" class="btn text-white" style="background:var(--brand);">
        <i class="fa fa-refresh me-1"></i> Reset Filters
      </a>
    </div>

  <?php elseif ($view === 'list'): ?>
    <!-- LIST VIEW -->
    <div class="list-wrap">
      <?php foreach ($products as $p): ?>
        <div class="list-card">
          <a href="product.php?id=<?= (int)$p['id'] ?>" class="list-thumb">
            <img src="<?= htmlspecialchars(img($p['image'])) ?>" alt="">
            <?php if (!empty($p['discount'])): ?>
              <span class="list-badge"><?= (int)$p['discount'] ?>% OFF</span>
            <?php endif; ?>
          </a>

          <div class="list-info">
            <h6><a href="product.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a></h6>
            <?php if (!empty($p['cat_name'])): ?>
              <div class="list-cat">
                <i class="fa <?= htmlspecialchars($p['cat_icon'] ?: 'fa-tag') ?>"></i>
                <?= htmlspecialchars($p['cat_name']) ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($p['description'])): ?>
              <p class="list-desc"><?= htmlspecialchars(mb_substr($p['description'], 0, 110)) ?><?= mb_strlen($p['description']) > 110 ? '…' : '' ?></p>
            <?php endif; ?>
          </div>

          <div class="list-buy">
            <div class="list-price"><?= money($p['price']) ?></div>
            <?php if (!empty($p['old_price'])): ?>
              <div class="list-old"><?= money($p['old_price']) ?></div>
            <?php endif; ?>
            <form action="cart/cart_add.php" method="post" class="mt-2">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-sm btn-add w-100">
                <i class="fa fa-cart-plus me-1"></i> Add to Cart
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <!-- GRID VIEW -->
    <div class="row g-3">
      <?php foreach ($products as $p): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="p-card">
            <a href="product.php?id=<?= (int)$p['id'] ?>" class="wish"><i class="fa fa-heart"></i></a>

            <?php if (!empty($p['discount'])): ?>
              <span class="grid-badge"><?= (int)$p['discount'] ?>% OFF</span>
            <?php endif; ?>

            <a href="product.php?id=<?= (int)$p['id'] ?>" class="grid-img">
              <img src="<?= htmlspecialchars(img($p['image'])) ?>" alt="">
            </a>

            <div class="body">
              <?php if (!empty($p['cat_name'])): ?>
                <div class="grid-cat">
                  <i class="fa <?= htmlspecialchars($p['cat_icon'] ?: 'fa-tag') ?>"></i>
                  <?= htmlspecialchars($p['cat_name']) ?>
                </div>
              <?php endif; ?>

              <h6><?= htmlspecialchars($p['name']) ?></h6>

              <div>
                <span class="price"><?= money($p['price']) ?></span>
                <?php if (!empty($p['old_price'])): ?>
                  <span class="old"><?= money($p['old_price']) ?></span>
                <?php endif; ?>
              </div>

              <form action="cart/cart_add.php" method="post" class="mt-2">
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
  <?php endif; ?>

  <!-- ============ PAGINATION ============ -->
  <?php if ($pages > 1): ?>
    <nav class="mt-4">
      <ul class="pagination justify-content-center">
        <?php
        $qs = $_GET; unset($qs['page']);
        $qsStr = http_build_query($qs);
        $qsStr = $qsStr ? $qsStr . '&' : '';
        ?>

        <?php if ($page > 1): ?>
          <li class="page-item">
            <a class="page-link" href="?<?= $qsStr ?>page=<?= $page-1 ?>">
              <i class="fa fa-chevron-left"></i>
            </a>
          </li>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end   = min($pages, $page + 2);
        if ($start > 1) echo '<li class="page-item"><a class="page-link" href="?'.$qsStr.'page=1">1</a></li><li class="page-item disabled"><span class="page-link">…</span></li>';
        for ($i = $start; $i <= $end; $i++):
        ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= $qsStr ?>page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($end < $pages) echo '<li class="page-item disabled"><span class="page-link">…</span></li><li class="page-item"><a class="page-link" href="?'.$qsStr.'page='.$pages.'">'.$pages.'</a></li>'; ?>

        <?php if ($page < $pages): ?>
          <li class="page-item">
            <a class="page-link" href="?<?= $qsStr ?>page=<?= $page+1 ?>">
              <i class="fa fa-chevron-right"></i>
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  <?php endif; ?>

</div>

<style>
/* Listing hero */
.listing-hero{
  background:linear-gradient(90deg,var(--brand) 0%,var(--brand-dark) 100%);
  color:#fff;border-radius:12px;padding:20px 24px;
  box-shadow:0 4px 14px rgba(91,43,140,.15);
}
.listing-hero h3{font-weight:800;color:#fff;margin:0;}
.listing-search{
  display:flex;align-items:center;gap:10px;
  background:rgba(255,255,255,.15);border-radius:24px;
  padding:6px 14px;color:#fff;
}
.listing-search input{
  border:none;background:transparent;outline:none;
  color:#fff;font-size:.92rem;padding:6px 0;min-width:200px;
}
.listing-search input::placeholder{color:rgba(255,255,255,.7);}

/* Category chips */
.chip-row{display:flex;flex-wrap:wrap;gap:8px;}
.chip{
  display:inline-flex;align-items:center;gap:6px;
  background:#fff;color:#333;text-decoration:none;
  border-radius:20px;padding:7px 14px;font-size:.85rem;
  font-weight:600;box-shadow:0 2px 6px rgba(0,0,0,.05);
  border:1px solid transparent;transition:.2s;
}
.chip:hover{color:var(--brand);border-color:var(--brand);}
.chip.active{background:var(--brand);color:#fff;}
.chip i{font-size:.9rem;}

/* Toolbar */
.listing-toolbar{
  display:flex;justify-content:space-between;align-items:center;
  background:#fff;border-radius:10px;padding:10px 14px;
  box-shadow:0 2px 8px rgba(0,0,0,.05);flex-wrap:wrap;gap:10px;
}
.sort-pill{
  display:inline-block;padding:6px 14px;border-radius:20px;
  color:#555;text-decoration:none;font-size:.83rem;font-weight:600;
  background:#f4f4f7;transition:.2s;
}
.sort-pill:hover{background:#f6f0ff;color:var(--brand);}
.sort-pill.active{background:var(--brand);color:#fff;}

.view-toggle{display:flex;gap:4px;}
.vt-btn{
  width:34px;height:34px;display:flex;align-items:center;justify-content:center;
  background:#f4f4f7;border-radius:8px;color:#666;text-decoration:none;
  transition:.2s;
}
.vt-btn:hover{background:#f6f0ff;color:var(--brand);}
.vt-btn.active{background:var(--brand);color:#fff;}

/* Price filter */
.price-filter{
  display:flex;align-items:center;gap:8px;flex-wrap:wrap;
  background:#fff;border-radius:10px;padding:10px 14px;
  box-shadow:0 2px 8px rgba(0,0,0,.05);
}
.price-filter input[type=number]{
  width:100px;border:1px solid #e2e2e8;border-radius:6px;
  padding:6px 10px;font-size:.88rem;
}
.price-filter input:focus{border-color:var(--brand);outline:none;}

/* Grid product card extra */
.grid-img{display:block;overflow:hidden;}
.grid-img img{transition:transform .35s;}
.p-card:hover .grid-img img{transform:scale(1.06);}
.grid-badge{
  position:absolute;top:10px;left:10px;z-index:2;
  background:#e53935;color:#fff;
  padding:3px 8px;border-radius:4px;
  font-size:.72rem;font-weight:700;
}
.grid-cat{
  font-size:.72rem;color:#888;margin-bottom:4px;
}
.grid-cat i{color:var(--brand);margin-right:3px;}

/* List view */
.list-wrap{display:flex;flex-direction:column;gap:12px;}
.list-card{
  display:flex;gap:16px;background:#fff;
  border-radius:10px;padding:14px;
  box-shadow:0 2px 8px rgba(0,0,0,.05);
  transition:.2s;
}
.list-card:hover{transform:translateY(-2px);box-shadow:0 6px 14px rgba(0,0,0,.1);}
.list-thumb{
  position:relative;flex-shrink:0;
  width:130px;height:130px;border-radius:8px;overflow:hidden;
  display:block;background:#f6f0ff;
}
.list-thumb img{width:100%;height:100%;object-fit:cover;display:block;}
.list-badge{
  position:absolute;top:6px;left:6px;
  background:#e53935;color:#fff;
  padding:2px 6px;border-radius:3px;
  font-size:.68rem;font-weight:700;
}
.list-info{flex:1;min-width:0;}
.list-info h6{margin:0 0 4px;}
.list-info h6 a{color:#333;text-decoration:none;font-weight:700;font-size:1.05rem;}
.list-info h6 a:hover{color:var(--brand);}
.list-cat{font-size:.78rem;color:#888;margin-bottom:6px;}
.list-cat i{color:var(--brand);margin-right:4px;}
.list-desc{font-size:.82rem;color:#888;margin:0;line-height:1.4;}
.list-buy{text-align:right;flex-shrink:0;width:170px;}
.list-price{color:var(--brand);font-weight:800;font-size:1.15rem;}
.list-old{color:#999;text-decoration:line-through;font-size:.85rem;}

/* Empty state */
.empty-state{
  background:#fff;border-radius:12px;padding:44px 20px;
  text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.05);
}
.empty-state i{font-size:3rem;color:#ccc;margin-bottom:10px;display:block;}
.empty-state h5{font-weight:800;color:var(--brand);margin-bottom:4px;}

@media (max-width:576px){
  .list-card{flex-direction:column;}
  .list-thumb{width:100%;height:180px;}
  .list-buy{width:100%;text-align:left;margin-top:8px;}
  .listing-search input{min-width:120px;}
}
</style>

<?php require_once 'includes/footer.php'; ?>