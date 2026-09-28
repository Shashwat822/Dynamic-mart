<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

/* Language */
if (empty($_SESSION['lang']) || !in_array($_SESSION['lang'], ['en','np'], true)) {
    $_SESSION['lang'] = 'en';
}
$LANG = require __DIR__ . '/../lang/' . $_SESSION['lang'] . '.php';
function t($key) {
    global $LANG;
    return $LANG[$key] ?? $key;
}

/* Cart count */
$cartCount = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $q) $cartCount += (int)$q;
}

/* Seller state — is this user already a seller? */
$isSeller = false;
if (!empty($_SESSION['user_id'])) {
    try {
        $ss = $pdo->prepare("SELECT id, shop_name FROM sellers WHERE user_id = :uid LIMIT 1");
        $ss->execute([':uid' => $_SESSION['user_id']]);
        $row = $ss->fetch();
        if ($row) {
            $isSeller = true;
            $_SESSION['seller_id']   = (int)$row['id'];
            $_SESSION['seller_name'] = $row['shop_name'];
        }
    } catch (Exception $e) {
        /* sellers table may not exist yet — ignore */
        $isSeller = !empty($_SESSION['seller_id']);
    }
} else {
    $isSeller = !empty($_SESSION['seller_id']);
}

/* Categories — capped at 10 parents so sidebar stays short */
$parents  = [];
$children = [];
$allCats = $pdo->query("
    SELECT id, name, icon, parent_id
    FROM categories
    WHERE parent_id IS NULL
    ORDER BY name
    LIMIT 10
")->fetchAll();

foreach ($allCats as $c) {
    $parents[] = $c;
}

/* Children for those parents */
if ($parents) {
    $pids = array_column($parents, 'id');
    $in   = implode(',', array_fill(0, count($pids), '?'));
    $chld = $pdo->prepare("SELECT id, name, icon, parent_id FROM categories WHERE parent_id IN ($in) ORDER BY name");
    $chld->execute($pids);
    foreach ($chld->fetchAll() as $c) {
        $children[$c['parent_id']][] = $c;
    }
}

/* Helpers */
$currency = 'Rs.';
function money($n, $cur = 'Rs.') { return $cur . ' ' . number_format((float)$n, 0); }
function img($p) {
    if (!$p) return 'https://via.placeholder.com/400x400?text=No+Image';
    return $p;
}

$base = '/mart/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars(t('site_name')) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{
  --brand:#5b2b8c;
  --brand-dark:#4a2175;
  --accent:#ff6a00;
}
body{background:#f4f4f7;font-family:system-ui,'Segoe UI',Roboto,'Noto Sans Devanagari',sans-serif;}

/* TOP BAR */
.topbar{background:var(--brand);color:#fff;padding:10px 0;}
.topbar .logo{font-weight:800;font-size:1.4rem;display:flex;align-items:center;gap:8px;color:#fff;}
.topbar .logo i{background:#fff;color:var(--brand);border-radius:6px;padding:4px 6px;}
.search-box{position:relative;}
.search-box input{border-radius:25px;border:none;padding:8px 40px 8px 16px;width:100%;}
.search-box i{position:absolute;right:14px;top:9px;color:#888;}
.topbar a{color:#fff;text-decoration:none;margin-left:18px;font-weight:500;}
.topbar a:hover{opacity:.85;}
.lang-btn{background:rgba(255,255,255,.15);padding:4px 10px;border-radius:20px;}

/* SELLER BUTTON (orange) */
.seller-btn{
  background:var(--accent);
  color:#fff !important;
  padding:6px 14px !important;
  border-radius:20px;
  font-weight:600;
  box-shadow:0 2px 6px rgba(255,106,0,.3);
  transition:.2s;
}
.seller-btn:hover{background:#e65f00;opacity:1 !important;}
.seller-btn i{margin-right:4px;}

/* CATEGORIES SIDEBAR */
.cat-menu{background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);}
.cat-menu .title{background:var(--brand);color:#fff;padding:12px 16px;font-weight:700;font-size:1.05rem;}
.cat-menu ul{list-style:none;margin:0;padding:0;}
.cat-menu > ul > li{border-bottom:1px solid #eee;}
.cat-menu > ul > li > a{display:flex;justify-content:space-between;align-items:center;
  padding:11px 16px;color:#333;text-decoration:none;font-weight:500;font-size:.92rem;}
.cat-menu > ul > li > a:hover{background:#f6f0ff;color:var(--brand);}
.cat-menu > ul > li > a.active{background:#f6f0ff;color:var(--brand);font-weight:700;}
.submenu{display:none;background:#faf7ff;}
.submenu a{display:block;padding:7px 28px;color:#555;text-decoration:none;font-size:.88rem;}
.submenu a:hover{color:var(--brand);}
.cat-menu li:hover .submenu{display:block;}

/* HERO */
.hero{border-radius:10px;overflow:hidden;position:relative;background:#000;height:400px;}
.hero img{width:100%;height:100%;object-fit:cover;display:block;}
.hero .carousel-inner,
.hero .carousel-item{height:100%;}

/* SIDE WIDGET */
.side-widget{background:#fff;border-radius:10px;overflow:hidden;
  box-shadow:0 2px 8px rgba(0,0,0,.05);height:400px;display:flex;flex-direction:column;}
.side-widget .title{background:var(--brand);color:#fff;padding:12px 16px;
  font-weight:700;font-size:1.05rem;flex-shrink:0;}
.mp-track{position:relative;flex:1;overflow:hidden;}
.mp-slide{position:absolute;inset:0;padding:12px;text-align:center;
  opacity:0;transform:translateY(15px);transition:opacity .6s, transform .6s;pointer-events:none;}
.mp-slide.active{opacity:1;transform:translateY(0);pointer-events:auto;}
.mp-slide img{width:100%;height:140px;object-fit:cover;border-radius:6px;}
.mp-slide h6{font-size:.92rem;margin:8px 0 3px;font-weight:700;}
.mp-slide p{font-size:.76rem;color:#777;margin:0 0 6px;}
.mp-slide .price{color:var(--brand);font-weight:700;font-size:1rem;}
.mp-slide .old{color:#999;text-decoration:line-through;font-size:.78rem;margin-left:6px;}
.mp-slide .off{color:#e53935;font-size:.75rem;margin-left:6px;font-weight:600;}
.mp-slide .add{background:var(--brand);color:#fff;border:none;width:100%;
  padding:6px;border-radius:4px;font-size:.85rem;margin-top:6px;}
.mp-slide .add:hover{background:var(--brand-dark);}
.mp-dots{display:flex;justify-content:center;gap:6px;padding:6px 0;background:#fff;flex-shrink:0;}
.mp-dots span{width:7px;height:7px;border-radius:50%;background:#ccc;cursor:pointer;transition:.2s;}
.mp-dots span.active{background:var(--brand);width:18px;border-radius:4px;}

.wish{position:absolute;top:18px;right:18px;background:#fff;border-radius:50%;
  width:28px;height:28px;display:flex;align-items:center;justify-content:center;
  color:var(--brand);box-shadow:0 2px 6px rgba(0,0,0,.15);z-index:2;font-size:.8rem;}

/* SECTION HEAD */
.section-head{display:flex;justify-content:space-between;align-items:center;
  border-bottom:3px solid var(--brand);padding-bottom:6px;margin:28px 0 18px;}
.section-head h4{color:var(--brand);font-weight:800;margin:0;}
.section-head a{color:#333;text-decoration:none;font-weight:600;font-size:.9rem;}
.section-head a:hover{color:var(--brand);}

/* PRODUCT CARD */
.p-card{background:#fff;border-radius:10px;overflow:hidden;
  box-shadow:0 2px 8px rgba(0,0,0,.05);transition:.2s;height:100%;position:relative;}
.p-card:hover{transform:translateY(-3px);box-shadow:0 6px 14px rgba(0,0,0,.1);}
.p-card img{width:100%;height:180px;object-fit:cover;}
.p-card .body{padding:12px;}
.p-card h6{font-size:.95rem;font-weight:600;margin:0 0 6px;}
.p-card .price{color:var(--brand);font-weight:700;}
.p-card .old{color:#999;text-decoration:line-through;font-size:.85rem;margin-left:6px;}
.p-card .off{color:#e53935;font-size:.8rem;margin-left:6px;font-weight:600;}
.p-card .btn-add{background:var(--brand);color:#fff;border:none;}
.p-card .btn-add:hover{background:var(--brand-dark);color:#fff;}

/* FOOTER */
.mart-footer{background:var(--brand);color:#fff;}
.mart-footer h6{font-weight:700;font-size:1rem;margin-bottom:14px;}
.mart-footer ul{list-style:none;padding:0;margin:0;}
.mart-footer ul li{margin-bottom:8px;font-size:.9rem;}
.mart-footer ul li a,.mart-footer ul li{color:#e8dff5;text-decoration:none;}
.mart-footer ul li a:hover{color:#fff;text-decoration:underline;}
.mart-footer ul li i{width:18px;margin-right:6px;}
.pay-badge{display:inline-block;background:#fff;color:var(--brand);
  border-radius:4px;padding:1px 8px;font-size:.75rem;font-weight:700;
  margin-right:6px;min-width:38px;text-align:center;}
.social a{display:inline-flex;align-items:center;justify-content:center;
  width:34px;height:34px;border-radius:50%;
  background:rgba(255,255,255,.15);color:#fff;margin-right:8px;
  text-decoration:none;font-size:.95rem;}
.social a:hover{background:#fff;color:var(--brand);}
.store-badge{display:flex;align-items:center;gap:10px;background:#000;color:#fff;
  border-radius:8px;padding:6px 12px;text-decoration:none;width:170px;
  margin-bottom:8px;border:1px solid rgba(255,255,255,.2);}
.store-badge i{font-size:1.5rem;}
.store-badge small{display:block;font-size:.6rem;line-height:1;opacity:.8;}
.store-badge strong{display:block;font-size:.95rem;line-height:1.1;}
.store-badge:hover{color:#fff;opacity:.9;}

/* Mobile tweak */
@media (max-width:768px){
  .topbar .logo{font-size:1.1rem;}
  .topbar a{margin-left:10px;font-size:.85rem;}
  .seller-btn{padding:4px 10px !important;font-size:.8rem;}
}
</style>
</head>
<body>

<!-- ================= TOP BAR ================= -->
<div class="topbar">
  <div class="container d-flex align-items-center">

    <a href="<?= $base ?>index.php" class="logo text-decoration-none">
      <i class="fa fa-desktop"></i> <?= htmlspecialchars(t('site_name')) ?>
    </a>

    <div class="mx-4 flex-grow-1 search-box" style="max-width:560px;">
      <form action="<?= $base ?>search.php" method="get">
        <input type="text" name="q" placeholder="<?= htmlspecialchars(t('search_ph')) ?>"
               value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        <i class="fa fa-search"></i>
      </form>
    </div>

    <div class="ms-auto d-flex align-items-center">

      <!-- SELLER BUTTON -->
      <?php if ($isSeller): ?>
        <a href="<?= $base ?>seller/dashboard.php" class="seller-btn">
          <i class="fa fa-store"></i> Seller Dashboard
        </a>
      <?php else: ?>
        <a href="<?= $base ?>seller/login.php" class="seller-btn">
          <i class="fa fa-store"></i> Become a Seller
        </a>
      <?php endif; ?>

      <a href="<?= $base ?>cart/cart.php">
        <i class="fa fa-shopping-cart"></i> <?= htmlspecialchars(t('cart')) ?>
        <?php if ($cartCount): ?>
          <span class="badge bg-warning text-dark"><?= $cartCount ?></span>
        <?php endif; ?>
      </a>

      <a href="<?= $base ?>lang.php?set=<?= $_SESSION['lang'] === 'en' ? 'np' : 'en' ?>" class="lang-btn">
        <i class="fa fa-globe"></i>
        <?= $_SESSION['lang'] === 'en' ? 'EN | नेपाली' : 'नेपाली | EN' ?>
      </a>

      <?php if (!empty($_SESSION['user_name'])): ?>
        <a href="<?= $base ?>cart/cart.php">
          <i class="fa fa-user"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
        </a>
        <a href="<?= $base ?>logout.php">
          <i class="fa fa-sign-out-alt"></i> <?= htmlspecialchars(t('logout')) ?>
        </a>
      <?php else: ?>
        <a href="<?= $base ?>login.php">
          <i class="fa fa-user"></i> <?= htmlspecialchars(t('login')) ?>
        </a>
      <?php endif; ?>
    </div>

  </div>
</div>
<!-- =============== /TOP BAR ================== -->