<?php
require_once 'includes/header.php';

$q       = trim($_GET['q'] ?? '');
$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$products = [];
$total    = 0;
$pages    = 1;
$didYouMean = '';

/* ============================================================
   TYPO-TOLERANT SEARCH HELPERS
   ============================================================ */
function edit_distance($a, $b) {
    $a = mb_strtolower($a);
    $b = mb_strtolower($b);
    $la = mb_strlen($a);
    $lb = mb_strlen($b);

    if ($la === 0) return $lb;
    if ($lb === 0) return $la;

    $prev = range(0, $lb);
    for ($i = 1; $i <= $la; $i++) {
        $cur = [$i];
        for ($j = 1; $j <= $lb; $j++) {
            $cost = (mb_substr($a, $i - 1, 1) === mb_substr($b, $j - 1, 1)) ? 0 : 1;
            $cur[$j] = min(
                $cur[$j - 1] + 1,
                $prev[$j] + 1,
                $prev[$j - 1] + $cost
            );
        }
        $prev = $cur;
    }
    return $prev[$lb];
}

function similarity($a, $b) {
    $a = mb_strtolower($a);
    $b = mb_strtolower($b);
    $max = max(mb_strlen($a), mb_strlen($b));
    if ($max === 0) return 1;
    return 1 - (edit_distance($a, $b) / $max);
}

function words($s) {
    $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
    $parts = preg_split('/\s+/u', trim($s));
    return array_values(array_filter($parts, fn($w) => mb_strlen($w) >= 2));
}

/* ============================================================
   PERFORM THE SEARCH
   ============================================================ */
if ($q !== '') {
    $like   = '%' . $q . '%';
    $words  = words($q);

    /* ---------- 1) Direct LIKE search ---------- */
    $sql = "
        SELECT id, name, description, price, old_price, discount, image, category_id
        FROM products
        WHERE name LIKE :q1 OR description LIKE :q2
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':q1' => $like, ':q2' => $like]);
    $direct = $stmt->fetchAll();

    /* ---------- 2) Fuzzy fallback ---------- */
    $fuzzy = [];
    if (count($direct) < 5 && $words) {
        $all = $pdo->query("
            SELECT id, name, description, price, old_price, discount, image, category_id
            FROM products
        ")->fetchAll();

        foreach ($all as $row) {
            $hay       = mb_strtolower($row['name'] . ' ' . ($row['description'] ?? ''));
            $hayWords  = words($hay);
            $bestScore = 0;

            foreach ($words as $w) {
                foreach ($hayWords as $hw) {
                    $s = similarity($w, $hw);
                    if ($s > $bestScore) $bestScore = $s;

                    if (mb_strpos($hw, $w) !== false || mb_strpos($w, $hw) !== false) {
                        if (0.85 > $bestScore) $bestScore = 0.85;
                    }
                }
            }

            if ($bestScore >= 0.72) {
                $row['_score'] = $bestScore;
                $fuzzy[] = $row;
            }
        }

        usort($fuzzy, fn($a, $b) => $b['_score'] <=> $a['_score']);
    }

    /* ---------- 3) Merge ---------- */
    $seen = [];
    $merged = [];
    foreach ($direct as $row) {
        $row['_score'] = 1;
        $seen[$row['id']] = true;
        $merged[] = $row;
    }
    foreach ($fuzzy as $row) {
        if (!isset($seen[$row['id']])) {
            $seen[$row['id']] = true;
            $merged[] = $row;
        }
    }

    /* ---------- 4) Rank ---------- */
    $qLower = mb_strtolower($q);
    usort($merged, function ($a, $b) use ($qLower) {
        $aName = mb_strtolower($a['name']);
        $bName = mb_strtolower($b['name']);

        $aInName = mb_strpos($aName, $qLower) !== false ? 1 : 0;
        $bInName = mb_strpos($bName, $qLower) !== false ? 1 : 0;
        if ($aInName !== $bInName) return $bInName - $aInName;

        if ($a['_score'] !== $b['_score']) return $b['_score'] <=> $a['_score'];

        $aDisc = (float)($a['discount'] ?? 0);
        $bDisc = (float)($b['discount'] ?? 0);
        return $bDisc <=> $aDisc;
    });

    /* ---------- 5) Paginate ---------- */
    $total    = count($merged);
    $pages    = max(1, ceil($total / $perPage));
    $products = array_slice($merged, $offset, $perPage);

    /* ---------- 6) Did you mean ---------- */
    if ($total > 0 && $words) {
        $topName = mb_strtolower($products[0]['name']);
        $qWord   = mb_strtolower($words[0]);
        if (mb_strpos($topName, $qWord) === false) {
            $topWords = words($topName);
            $best = '';
            $bestScore = 0;
            foreach ($topWords as $tw) {
                $s = similarity($qWord, $tw);
                if ($s > $bestScore && $s < 1) {
                    $bestScore = $s;
                    $best      = $tw;
                }
            }
            if ($best && $bestScore >= 0.6) {
                $didYouMean = $best;
            }
        }
    }
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

/* ---------- Popular fallback ---------- */
$popular = [];
if ($q !== '' && empty($products)) {
    $popular = $pdo->query("
        SELECT id, name, price, old_price, discount, image
        FROM products
        ORDER BY IFNULL(discount,0) DESC, created_at DESC
        LIMIT 8
    ")->fetchAll();
}
?>

<div class="container my-4">

  <div class="search-hero mb-3">
    <form method="get" action="search.php" class="search-hero-form">
      <i class="fa fa-search"></i>
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
             placeholder="Search products, brands, categories…"
             autofocus autocomplete="off">
      <button type="submit" class="btn-search-hero">Search</button>
    </form>
  </div>

  <?php if ($chipCats): ?>
    <div class="chip-row mb-3">
      <a href="products.php" class="chip <?= $q === '' ? 'active' : '' ?>">
        <i class="fa fa-th"></i> All
      </a>
      <?php foreach ($chipCats as $c): ?>
        <a href="category.php?id=<?= (int)$c['id'] ?>" class="chip">
          <i class="fa <?= htmlspecialchars($c['icon'] ?: 'fa-tag') ?>"></i>
          <?= htmlspecialchars($c['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="section-head">
    <h4 class="mb-0">
      <?php if ($q === ''): ?>
        Start typing to search
      <?php else: ?>
        Results for <em>"<?= htmlspecialchars($q) ?>"</em>
        <small class="text-muted" style="font-size:.8rem;">
          (<?= $total ?> item<?= $total === 1 ? '' : 's' ?>)
        </small>
      <?php endif; ?>
    </h4>
    <?php if ($q !== ''): ?>
      <a href="products.php">Browse all products <i class="fa fa-arrow-right"></i></a>
    <?php endif; ?>
  </div>

  <?php if ($didYouMean && $didYouMean !== $q): ?>
    <div class="dym-bar mb-3">
      <i class="fa fa-lightbulb"></i>
      Did you mean
      <a href="?q=<?= urlencode($didYouMean) ?>"><strong><?= htmlspecialchars($didYouMean) ?></strong></a>?
    </div>
  <?php endif; ?>

  <?php if ($q === ''): ?>
    <div class="alert alert-info">
      Try searching for something like <strong>phone</strong>, <strong>shoes</strong>, <strong>tv</strong>, or <strong>kurta</strong>.
    </div>

  <?php elseif (!$products): ?>
    <div class="empty-state">
      <i class="fa fa-search-minus"></i>
      <h5>No results for "<?= htmlspecialchars($q) ?>"</h5>
      <p class="text-muted mb-3">Check the spelling or try a different keyword.</p>

      <?php if ($popular): ?>
        <h6 class="text-start mt-4 mb-2" style="color:var(--brand);font-weight:800;">
          <i class="fa fa-fire me-1"></i> You might like these
        </h6>
        <div class="row g-3 text-start">
          <?php foreach ($popular as $p): ?>
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
    </div>

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

    <?php if ($pages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination justify-content-center">
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
              <a class="page-link" href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

</div>

<style>
.search-hero{
  background:linear-gradient(90deg,var(--brand) 0%,var(--brand-dark) 100%);
  border-radius:12px;padding:20px;
  box-shadow:0 4px 14px rgba(91,43,140,.15);
}
.search-hero-form{
  display:flex;align-items:center;gap:10px;
  background:#fff;border-radius:30px;padding:6px 6px 6px 18px;
}
.search-hero-form i{color:var(--brand);font-size:1.05rem;}
.search-hero-form input{
  flex:1;border:none;outline:none;
  font-size:1rem;padding:8px 0;
}
.btn-search-hero{
  background:var(--brand);color:#fff;border:none;
  border-radius:24px;padding:9px 22px;font-weight:700;
}
.btn-search-hero:hover{background:var(--brand-dark);color:#fff;}

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

.dym-bar{
  background:#fff7e0;color:#8a6d1a;
  border:1px solid #f5dfa1;border-radius:8px;
  padding:10px 14px;font-size:.9rem;
}
.dym-bar i{color:#d4a418;margin-right:6px;}
.dym-bar a{color:#8a6d1a;}

.empty-state{
  background:#fff;border-radius:12px;padding:36px 20px;
  text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.05);
}
.empty-state > i{
  font-size:3rem;color:#ccc;margin-bottom:10px;display:block;
}
.empty-state h5{
  font-weight:800;color:var(--brand);margin-bottom:4px;
}
</style>

<?php require_once 'includes/footer.php'; ?>