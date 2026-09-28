<?php
require_once 'includes/header.php';

$mostPurchased = $pdo->query("
    SELECT id, name, description, price, old_price, discount, image
    FROM products
    ORDER BY IFNULL(discount,0) DESC, created_at DESC
    LIMIT 6
")->fetchAll();

$newArrivals = $pdo->query("
    SELECT id, name, price, old_price, discount, image
    FROM products
    ORDER BY created_at DESC
    LIMIT 8
")->fetchAll();

$featuredCats = $pdo->query("
    SELECT c.id, c.name, c.icon,
           (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS cnt
    FROM categories c
    WHERE c.parent_id IS NULL
    ORDER BY cnt DESC, c.name
    LIMIT 12
")->fetchAll();

$recentAds = $pdo->query("
    SELECT p.id, p.name, p.price, p.image, c.name AS cat_name, c.icon AS cat_icon
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll();

$promoted = $pdo->query("
    SELECT id, name, description, price, old_price, discount, image
    FROM products
    WHERE discount IS NOT NULL AND discount > 0
    ORDER BY discount DESC, created_at DESC
    LIMIT 4
")->fetchAll();

$discover = $pdo->query("
    SELECT p.id, p.name, p.price, p.image, c.name AS cat_name, c.icon AS cat_icon
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    ORDER BY RAND()
    LIMIT 8
")->fetchAll();

/* RECOMMENDED FOR YOU */
$recCats  = [];
$recTitle = 'Popular Right Now';
$recSub   = 'Trending products across Dynamic Mart';

if (!empty($_SESSION['user_id'])) {
    $u = $pdo->prepare("
        SELECT DISTINCT p.category_id
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        JOIN products   p   ON p.id = oi.product_id
        WHERE o.user_id = :uid AND p.category_id IS NOT NULL
        ORDER BY o.created_at DESC
        LIMIT 6
    ");
    $u->execute([':uid' => $_SESSION['user_id']]);
    foreach ($u->fetchAll() as $r) $recCats[] = (int)$r['category_id'];
}

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $c   = $pdo->prepare("SELECT DISTINCT category_id FROM products WHERE id IN ($in) AND category_id IS NOT NULL");
    $c->execute($ids);
    foreach ($c->fetchAll() as $r) $recCats[] = (int)$r['category_id'];
}

$recCats = array_values(array_unique(array_filter($recCats)));
$recCats = array_slice($recCats, 0, 5);

if ($recCats) {
    $in = implode(',', array_fill(0, count($recCats), '?'));
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.old_price, p.discount, p.image,
               c.name AS cat_name, c.icon AS cat_icon
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.category_id IN ($in)
        ORDER BY RAND()
        LIMIT 8
    ");
    $stmt->execute($recCats);
    $recommended = $stmt->fetchAll();
    $recTitle = 'Recommended For You';
    $recSub   = 'Based on your activity at Dynamic Mart';
} else {
    $recommended = $pdo->query("
        SELECT p.id, p.name, p.price, p.old_price, p.discount, p.image,
               c.name AS cat_name, c.icon AS cat_icon
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        ORDER BY IFNULL(p.discount,0) DESC, RAND()
        LIMIT 8
    ")->fetchAll();
}
?>

<div class="container my-4">

  <div class="post-ad-banner mb-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <h5 class="mb-1"><i class="fa fa-bullhorn me-2"></i> <?= htmlspecialchars(t('post_ad_title')) ?></h5>
        <div class="small opacity-75"><?= htmlspecialchars(t('post_ad_sub')) ?></div>
      </div>
      <a href="register.php" class="btn btn-light fw-bold" style="color:var(--brand);">
        <i class="fa fa-plus-circle me-1"></i> <?= htmlspecialchars(t('post_ad_btn')) ?>
      </a>
    </div>
  </div>

  <div class="row g-3">

    <!-- Categories -->
    <div class="col-lg-3">
      <div class="cat-menu">
        <div class="title"><i class="fa fa-th-large me-2"></i> <?= htmlspecialchars(t('categories')) ?></div>
        <ul>
          <?php foreach ($parents as $p): ?>
            <li>
              <a href="category.php?id=<?= (int)$p['id'] ?>">
                <span><i class="fa <?= htmlspecialchars($p['icon'] ?: 'fa-tag') ?> me-2"></i><?= htmlspecialchars($p['name']) ?></span>
                <?php if (!empty($children[$p['id']])): ?><i class="fa fa-chevron-right" style="font-size:.75rem;"></i><?php endif; ?>
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

    <!-- Hero + mini tiles -->
    <div class="col-lg-6">
      <div id="heroCarousel" class="carousel slide hero" data-bs-ride="carousel">
        <div class="carousel-inner">
          <div class="carousel-item active"><img src="https://picsum.photos/seed/hero1/900/420" alt="Sale"></div>
          <div class="carousel-item"><img src="https://picsum.photos/seed/hero2/900/420" alt="Sale"></div>
          <div class="carousel-item"><img src="https://picsum.photos/seed/hero3/900/420" alt="Sale"></div>
        </div>
        <button class="carousel-control-prev" data-bs-target="#heroCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" data-bs-target="#heroCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon"></span>
        </button>
      </div>

      <div class="mini-tiles">
        <a href="products.php" class="mini-tile">
          <div class="mt-icon"><i class="fa fa-bolt"></i></div>
          <div>
            <div class="mt-title">Flash Deals</div>
            <div class="mt-sub">Up to 40% off</div>
          </div>
        </a>
        <a href="products.php" class="mini-tile">
          <div class="mt-icon"><i class="fa fa-truck-fast"></i></div>
          <div>
            <div class="mt-title">Free Delivery</div>
            <div class="mt-sub">On orders Rs. 5000+</div>
          </div>
        </a>
        <a href="register.php" class="mini-tile">
          <div class="mt-icon"><i class="fa fa-store"></i></div>
          <div>
            <div class="mt-title">Become a Seller</div>
            <div class="mt-sub">Start selling free</div>
          </div>
        </a>
      </div>
    </div>

    <!-- Most Purchased -->
    <div class="col-lg-3">
      <div class="side-widget">
        <div class="title"><i class="fa fa-fire me-2"></i> <?= htmlspecialchars(t('most_purchased')) ?></div>
        <div class="mp-track" id="mpTrack">
          <?php foreach ($mostPurchased as $i => $p): ?>
            <div class="mp-slide <?= $i === 0 ? 'active' : '' ?>">
              <a href="product.php?id=<?= (int)$p['id'] ?>" class="wish"><i class="fa fa-heart"></i></a>
              <a href="product.php?id=<?= (int)$p['id'] ?>">
                <img src="<?= htmlspecialchars(img($p['image'])) ?>" alt="">
              </a>
              <h6><?= htmlspecialchars($p['name']) ?></h6>
              <p><?= htmlspecialchars(mb_substr($p['description'] ?? '', 0, 55)) ?>...</p>
              <div>
                <span class="price"><?= money($p['price']) ?></span>
                <?php if (!empty($p['old_price'])): ?><span class="old"><?= money($p['old_price']) ?></span><?php endif; ?>
                <?php if (!empty($p['discount'])): ?><span class="off"><?= (int)$p['discount'] ?>% off</span><?php endif; ?>
              </div>
              <form action="cart/cart_add.php" method="post">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="add"><i class="fa fa-cart-plus me-1"></i> <?= htmlspecialchars(t('add_cart')) ?></button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mp-dots" id="mpDots">
          <?php foreach ($mostPurchased as $i => $p): ?>
            <span class="<?= $i === 0 ? 'active' : '' ?>" data-i="<?= $i ?>"></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- AD STRIP -->
  <div class="ad-strip my-3">
    <div class="ad-slide active"><img src="https://picsum.photos/seed/ad1/1200/160" alt="Ad"></div>
    <div class="ad-slide"><img src="https://picsum.photos/seed/ad2/1200/160" alt="Ad"></div>
    <div class="ad-slide"><img src="https://picsum.photos/seed/ad3/1200/160" alt="Ad"></div>
    <div class="ad-dots"><span class="active"></span><span></span><span></span></div>
  </div>

  <!-- PROMOTED ADS -->
  <?php if ($promoted): ?>
    <div class="section-head mt-3">
      <h4><i class="fa fa-star me-2"></i> Promoted Ads</h4>
      <a href="products.php"><?= htmlspecialchars(t('view_all')) ?> <i class="fa fa-arrow-right"></i></a>
    </div>

    <div class="row g-3">
      <?php foreach ($promoted as $p): ?>
        <div class="col-6 col-md-6 col-lg-3">
          <div class="promo-card">
            <a href="product.php?id=<?= (int)$p['id'] ?>" class="promo-img">
              <img src="<?= htmlspecialchars(img($p['image'])) ?>" alt="">
              <?php if (!empty($p['discount'])): ?><span class="promo-badge"><?= (int)$p['discount'] ?>% OFF</span><?php endif; ?>
            </a>
            <div class="promo-body">
              <h6><?= htmlspecialchars($p['name']) ?></h6>
              <p class="promo-desc"><?= htmlspecialchars(mb_substr($p['description'] ?? '', 0, 70)) ?>...</p>
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <span class="price"><?= money($p['price']) ?></span>
                  <?php if (!empty($p['old_price'])): ?><span class="old"><?= money($p['old_price']) ?></span><?php endif; ?>
                </div>
                <form action="cart/cart_add.php" method="post">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="btn btn-sm text-white" style="background:var(--brand);">
                    <i class="fa fa-cart-plus"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- SHOP BY CATEGORY -->
  <div class="section-head mt-3">
    <h4><?= htmlspecialchars(t('shop_by_cat')) ?></h4>
    <a href="products.php"><?= htmlspecialchars(t('view_all')) ?> <i class="fa fa-arrow-right"></i></a>
  </div>

  <div class="row g-3">
    <?php foreach ($featuredCats as $c): ?>
      <div class="col-4 col-md-3 col-lg-2">
        <a href="category.php?id=<?= (int)$c['id'] ?>" class="cat-tile">
          <div class="cat-icon"><i class="fa <?= htmlspecialchars($c['icon'] ?: 'fa-tag') ?>"></i></div>
          <div class="cat-name"><?= htmlspecialchars($c['name']) ?></div>
          <div class="cat-count"><?= (int)$c['cnt'] ?> <?= htmlspecialchars(t('ads')) ?></div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- NEW ARRIVALS -->
  <div class="section-head">
    <h4><?= htmlspecialchars(t('new_arrivals')) ?></h4>
    <a href="products.php"><?= htmlspecialchars(t('view_all')) ?> <i class="fa fa-arrow-right"></i></a>
  </div>

  <div class="row g-3">
    <?php foreach ($newArrivals as $p): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <div class="p-card">
          <a href="product.php?id=<?= (int)$p['id'] ?>" class="wish"><i class="fa fa-heart"></i></a>
          <a href="product.php?id=<?= (int)$p['id'] ?>"><img src="<?= htmlspecialchars(img($p['image'])) ?>" alt=""></a>
          <div class="body">
            <h6><?= htmlspecialchars($p['name']) ?></h6>
            <div>
              <span class="price"><?= money($p['price']) ?></span>
              <?php if (!empty($p['old_price'])): ?><span class="old"><?= money($p['old_price']) ?></span><?php endif; ?>
              <?php if (!empty($p['discount'])): ?><span class="off"><?= (int)$p['discount'] ?>% off</span><?php endif; ?>
            </div>
            <form action="cart/cart_add.php" method="post" class="mt-2">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn btn-sm w-100 btn-add">
                <i class="fa fa-cart-plus me-1"></i> <?= htmlspecialchars(t('add_cart')) ?>
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- RECENT ADS -->
  <div class="section-head">
    <h4><?= htmlspecialchars(t('recent_ads')) ?></h4>
    <a href="products.php"><?= htmlspecialchars(t('view_all')) ?> <i class="fa fa-arrow-right"></i></a>
  </div>

  <div class="row g-3">
    <?php foreach ($recentAds as $p): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a href="product.php?id=<?= (int)$p['id'] ?>" class="ad-row">
          <img src="<?= htmlspecialchars(img($p['image'])) ?>" alt="">
          <div class="ad-body">
            <div class="ad-title"><?= htmlspecialchars($p['name']) ?></div>
            <div class="ad-cat">
              <i class="fa <?= htmlspecialchars($p['cat_icon'] ?: 'fa-tag') ?>"></i>
              <?= htmlspecialchars($p['cat_name'] ?? 'Uncategorized') ?>
            </div>
            <div class="ad-price"><?= money($p['price']) ?></div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- DISCOVER MORE -->
  <?php if ($discover): ?>
    <div class="section-head mt-4">
      <h4><i class="fa fa-compass me-2"></i> Discover More</h4>
      <a href="products.php"><?= htmlspecialchars(t('view_all')) ?> <i class="fa fa-arrow-right"></i></a>
    </div>

    <div class="row g-3">
      <?php foreach ($discover as $p): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <a href="product.php?id=<?= (int)$p['id'] ?>" class="ad-row">
            <img src="<?= htmlspecialchars(img($p['image'])) ?>" alt="">
            <div class="ad-body">
              <div class="ad-title"><?= htmlspecialchars($p['name']) ?></div>
              <div class="ad-cat">
                <i class="fa <?= htmlspecialchars($p['cat_icon'] ?: 'fa-tag') ?>"></i>
                <?= htmlspecialchars($p['cat_name'] ?? 'Uncategorized') ?>
              </div>
              <div class="ad-price"><?= money($p['price']) ?></div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- RECOMMENDED FOR YOU -->
  <?php if ($recommended): ?>
    <div class="section-head mt-4">
      <h4><i class="fa fa-magic me-2"></i> <?= htmlspecialchars($recTitle) ?></h4>
      <a href="products.php"><?= htmlspecialchars(t('view_all')) ?> <i class="fa fa-arrow-right"></i></a>
    </div>
    <div class="small text-muted mb-2 ms-1"><?= htmlspecialchars($recSub) ?></div>

    <div class="row g-3">
      <?php foreach ($recommended as $p): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="p-card">
            <a href="product.php?id=<?= (int)$p['id'] ?>" class="wish"><i class="fa fa-heart"></i></a>
            <a href="product.php?id=<?= (int)$p['id'] ?>"><img src="<?= htmlspecialchars(img($p['image'])) ?>" alt=""></a>
            <div class="body">
              <h6><?= htmlspecialchars($p['name']) ?></h6>
              <div class="ad-cat" style="font-size:.75rem;color:#888;margin:2px 0 6px;">
                <i class="fa <?= htmlspecialchars($p['cat_icon'] ?: 'fa-tag') ?>" style="color:var(--brand);"></i>
                <?= htmlspecialchars($p['cat_name'] ?? '') ?>
              </div>
              <div>
                <span class="price"><?= money($p['price']) ?></span>
                <?php if (!empty($p['old_price'])): ?><span class="old"><?= money($p['old_price']) ?></span><?php endif; ?>
                <?php if (!empty($p['discount'])): ?><span class="off"><?= (int)$p['discount'] ?>% off</span><?php endif; ?>
              </div>
              <form action="cart/cart_add.php" method="post" class="mt-2">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm w-100 btn-add">
                  <i class="fa fa-cart-plus me-1"></i> <?= htmlspecialchars(t('add_cart')) ?>
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- WHY SHOP WITH US -->
  <div class="section-head mt-4">
    <h4><i class="fa fa-shield-alt me-2"></i> Why Shop With Us</h4>
  </div>

  <div class="row g-3">
    <div class="col-6 col-md-3">
      <div class="trust-card"><i class="fa fa-truck"></i><h6>Free Delivery</h6><p>On orders above Rs. 5,000 inside Kathmandu Valley.</p></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="trust-card"><i class="fa fa-money-bill-wave"></i><h6>Cash on Delivery</h6><p>Pay when the product reaches your doorstep.</p></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="trust-card"><i class="fa fa-rotate-left"></i><h6>Easy Returns</h6><p>7-day easy return policy on all products.</p></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="trust-card"><i class="fa fa-headset"></i><h6>24/7 Support</h6><p>Our team is always ready to help you.</p></div>
    </div>
  </div>

</div>

<style>
.post-ad-banner{background:linear-gradient(90deg,var(--brand) 0%,var(--brand-dark) 100%);
  color:#fff;border-radius:10px;padding:16px 20px;box-shadow:0 2px 8px rgba(0,0,0,.08);}

.cat-tile{display:block;background:#fff;border-radius:10px;text-align:center;
  padding:18px 8px;text-decoration:none;color:#333;
  box-shadow:0 2px 8px rgba(0,0,0,.05);transition:.2s;border:1px solid transparent;}
.cat-tile:hover{transform:translateY(-3px);box-shadow:0 6px 14px rgba(0,0,0,.1);
  border-color:var(--brand);color:var(--brand);}
.cat-tile .cat-icon{width:52px;height:52px;border-radius:50%;background:#f6f0ff;
  color:var(--brand);display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;margin:0 auto 8px;}
.cat-tile:hover .cat-icon{background:var(--brand);color:#fff;}
.cat-tile .cat-name{font-weight:700;font-size:.9rem;line-height:1.15;}
.cat-tile .cat-count{font-size:.75rem;color:#999;}

.ad-row{display:flex;gap:10px;background:#fff;border-radius:10px;padding:10px;
  text-decoration:none;color:#333;box-shadow:0 2px 8px rgba(0,0,0,.05);
  transition:.2s;height:100%;align-items:center;}
.ad-row:hover{transform:translateY(-3px);box-shadow:0 6px 14px rgba(0,0,0,.1);}
.ad-row img{width:72px;height:72px;object-fit:cover;border-radius:8px;flex-shrink:0;}
.ad-row .ad-body{min-width:0;}
.ad-row .ad-title{font-weight:600;font-size:.92rem;line-height:1.2;white-space:nowrap;
  overflow:hidden;text-overflow:ellipsis;}
.ad-row .ad-cat{font-size:.74rem;color:#888;margin:3px 0;}
.ad-row .ad-cat i{color:var(--brand);margin-right:4px;}
.ad-row .ad-price{color:var(--brand);font-weight:700;font-size:1rem;}

.ad-strip{position:relative;border-radius:10px;overflow:hidden;
  box-shadow:0 2px 8px rgba(0,0,0,.06);background:#fff;height:160px;}
.ad-slide{position:absolute;inset:0;opacity:0;transition:opacity .8s;pointer-events:none;}
.ad-slide.active{opacity:1;pointer-events:auto;}
.ad-slide img{width:100%;height:100%;object-fit:cover;display:block;}
.ad-dots{position:absolute;bottom:8px;left:50%;transform:translateX(-50%);
  display:flex;gap:6px;background:rgba(0,0,0,.35);padding:4px 10px;border-radius:20px;}
.ad-dots span{width:7px;height:7px;border-radius:50%;background:#fff;opacity:.5;cursor:pointer;transition:.2s;}
.ad-dots span.active{opacity:1;width:18px;border-radius:4px;}

.promo-card{background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05);
  transition:.2s;height:100%;}
.promo-card:hover{transform:translateY(-3px);box-shadow:0 6px 14px rgba(0,0,0,.1);}
.promo-img{display:block;position:relative;}
.promo-img img{width:100%;height:170px;object-fit:cover;display:block;}
.promo-badge{position:absolute;top:10px;left:10px;background:#e53935;color:#fff;
  padding:3px 8px;border-radius:4px;font-size:.72rem;font-weight:700;}
.promo-body{padding:12px;}
.promo-body h6{font-size:.95rem;font-weight:700;margin:0 0 4px;}
.promo-desc{font-size:.78rem;color:#777;margin:0 0 8px;}
.promo-body .price{color:var(--brand);font-weight:700;font-size:1rem;}
.promo-body .old{color:#999;text-decoration:line-through;font-size:.8rem;margin-left:6px;}

.trust-card{background:#fff;border-radius:10px;padding:20px 14px;text-align:center;
  box-shadow:0 2px 8px rgba(0,0,0,.05);transition:.2s;height:100%;}
.trust-card:hover{transform:translateY(-3px);box-shadow:0 6px 14px rgba(0,0,0,.1);}
.trust-card i{font-size:1.8rem;color:var(--brand);background:#f6f0ff;border-radius:50%;
  width:60px;height:60px;display:inline-flex;align-items:center;justify-content:center;
  margin-bottom:10px;}
.trust-card h6{font-weight:700;font-size:.95rem;margin:0 0 6px;}
.trust-card p{font-size:.8rem;color:#777;margin:0;}

/* HERO — smaller to match sidebar */
.hero{border-radius:10px;overflow:hidden;position:relative;background:#000;height:420px;}
.hero img{width:100%;height:100%;object-fit:cover;display:block;}
.hero .carousel-inner,
.hero .carousel-item{height:100%;}

/* SIDE WIDGET — matches hero */
.side-widget{background:#fff;border-radius:10px;overflow:hidden;
  box-shadow:0 2px 8px rgba(0,0,0,.05);height:420px;display:flex;flex-direction:column;}
.side-widget .title{background:var(--brand);color:#fff;padding:12px 16px;
  font-weight:700;font-size:1.05rem;flex-shrink:0;}
.mp-track{position:relative;flex:1;overflow:hidden;}
.mp-slide{position:absolute;inset:0;padding:12px;text-align:center;
  opacity:0;transform:translateY(15px);transition:opacity .6s, transform .6s;pointer-events:none;}
.mp-slide.active{opacity:1;transform:translateY(0);pointer-events:auto;}
.mp-slide img{width:100%;height:150px;object-fit:cover;border-radius:6px;}
.mp-slide h6{font-size:.95rem;margin:10px 0 4px;font-weight:700;}
.mp-slide p{font-size:.8rem;color:#777;margin:0 0 8px;}
.mp-slide .price{color:var(--brand);font-weight:700;font-size:1.05rem;}
.mp-slide .old{color:#999;text-decoration:line-through;font-size:.82rem;margin-left:6px;}
.mp-slide .off{color:#e53935;font-size:.78rem;margin-left:6px;font-weight:600;}
.mp-slide .add{background:var(--brand);color:#fff;border:none;width:100%;
  padding:7px;border-radius:4px;font-size:.88rem;margin-top:8px;}
.mp-slide .add:hover{background:var(--brand-dark);}
.mp-dots{display:flex;justify-content:center;gap:6px;padding:8px 0;background:#fff;flex-shrink:0;}
.mp-dots span{width:7px;height:7px;border-radius:50%;background:#ccc;cursor:pointer;transition:.2s;}
.mp-dots span.active{background:var(--brand);width:18px;border-radius:4px;}

/* MINI TILES */
.mini-tiles{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:12px;}
.mini-tile{background:#fff;border-radius:10px;overflow:hidden;
  box-shadow:0 2px 8px rgba(0,0,0,.05);text-decoration:none;color:#333;
  transition:.2s;display:flex;align-items:center;gap:10px;padding:12px;}
.mini-tile:hover{transform:translateY(-3px);box-shadow:0 6px 14px rgba(0,0,0,.1);color:var(--brand);}
.mini-tile .mt-icon{width:44px;height:44px;border-radius:50%;background:#f6f0ff;
  color:var(--brand);display:flex;align-items:center;justify-content:center;
  font-size:1.2rem;flex-shrink:0;}
.mini-tile:hover .mt-icon{background:var(--brand);color:#fff;}
.mini-tile .mt-title{font-weight:700;font-size:.9rem;line-height:1.15;}
.mini-tile .mt-sub{font-size:.74rem;color:#888;}

.wish{position:absolute;top:22px;right:22px;background:#fff;border-radius:50%;
  width:30px;height:30px;display:flex;align-items:center;justify-content:center;
  color:var(--brand);box-shadow:0 2px 6px rgba(0,0,0,.15);z-index:2;}

@media (max-width:768px){ .ad-strip{height:100px;} .mini-tiles{grid-template-columns:1fr;} }
</style>

<script>
(function(){
  const slides = document.querySelectorAll('#mpTrack .mp-slide');
  const dots   = document.querySelectorAll('#mpDots span');
  if (!slides.length) return;
  let cur = 0, timer;
  function show(i){
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    slides[i].classList.add('active');
    if (dots[i]) dots[i].classList.add('active');
    cur = i;
  }
  function next(){ show((cur + 1) % slides.length); }
  function start(){ clearInterval(timer); timer = setInterval(next, 3500); }
  dots.forEach(d => d.addEventListener('click', () => { show(parseInt(d.dataset.i,10)); start(); }));
  const track = document.getElementById('mpTrack');
  track.addEventListener('mouseenter', () => clearInterval(timer));
  track.addEventListener('mouseleave', start);
  start();
})();

(function(){
  const slides = document.querySelectorAll('.ad-slide');
  const dots   = document.querySelectorAll('.ad-dots span');
  if (slides.length < 2) return;
  let cur = 0, timer;
  function show(i){
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    slides[i].classList.add('active');
    if (dots[i]) dots[i].classList.add('active');
    cur = i;
  }
  function next(){ show((cur + 1) % slides.length); }
  function start(){ clearInterval(timer); timer = setInterval(next, 4500); }
  dots.forEach((d, i) => d.addEventListener('click', () => { show(i); start(); }));
  start();
})();
</script>

<?php require_once 'includes/footer.php'; ?>