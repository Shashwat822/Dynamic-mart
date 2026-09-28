<?php
require_once 'includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS cat_name, c.icon AS cat_icon
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = :id
");
$stmt->execute([':id' => $id]);
$p = $stmt->fetch();

if (!$p) {
    echo '<div class="container my-5"><div class="alert alert-warning">
          Product not found. <a href="index.php">Back to home</a>.</div></div>';
    require_once 'includes/footer.php';
    exit;
}

/* Related products (same category) */
$rel = $pdo->prepare("
    SELECT id, name, price, old_price, discount, image
    FROM products
    WHERE category_id = :cid AND id <> :id
    ORDER BY created_at DESC
    LIMIT 8
");
$rel->execute([':cid' => $p['category_id'], ':id' => $id]);
$related = $rel->fetchAll();

/* Track viewed categories for recommendations */
if (!empty($p['category_id'])) {
    if (!isset($_SESSION['viewed_cats'])) $_SESSION['viewed_cats'] = [];
    $cid = (int)$p['category_id'];
    $_SESSION['viewed_cats'] = array_values(array_unique(
        array_merge([$cid], $_SESSION['viewed_cats'])
    ));
    $_SESSION['viewed_cats'] = array_slice($_SESSION['viewed_cats'], 0, 6);
}

$inStock = (int)$p['stock'] > 0;
?>

<div class="container my-4">

  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="index.php">Home</a></li>
      <?php if ($p['cat_name']): ?>
        <li class="breadcrumb-item">
          <a href="category.php?id=<?= (int)$p['category_id'] ?>">
            <?= htmlspecialchars($p['cat_name']) ?>
          </a>
        </li>
      <?php endif; ?>
      <li class="breadcrumb-item active"><?= htmlspecialchars($p['name']) ?></li>
    </ol>
  </nav>

  <!-- MAIN PRODUCT PANEL -->
  <div class="row g-4 bg-white p-4 rounded shadow-sm">

    <!-- Left: image -->
    <div class="col-md-5">
      <div class="product-img-wrap">
        <img src="<?= htmlspecialchars(img($p['image'])) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
        <?php if (!empty($p['discount'])): ?>
          <span class="p-badge"><?= (int)$p['discount'] ?>% OFF</span>
        <?php endif; ?>
        <button class="p-wish" type="button" title="Add to wishlist">
          <i class="fa fa-heart"></i>
        </button>
      </div>

      <!-- Trust strip under image -->
      <div class="row g-2 mt-3">
        <div class="col-4">
          <div class="mini-trust"><i class="fa fa-truck"></i> Free Delivery</div>
        </div>
        <div class="col-4">
          <div class="mini-trust"><i class="fa fa-rotate-left"></i> 7-day Return</div>
        </div>
        <div class="col-4">
          <div class="mini-trust"><i class="fa fa-shield-alt"></i> Warranty</div>
        </div>
      </div>
    </div>

    <!-- Right: info -->
    <div class="col-md-7">
      <h1 class="product-title"><?= htmlspecialchars($p['name']) ?></h1>

      <?php if (!empty($p['cat_name'])): ?>
        <div class="product-cat">
          <i class="fa <?= htmlspecialchars($p['cat_icon'] ?: 'fa-tag') ?>"></i>
          <a href="category.php?id=<?= (int)$p['category_id'] ?>">
            <?= htmlspecialchars($p['cat_name']) ?>
          </a>
        </div>
      <?php endif; ?>

      <div class="product-price-row">
        <span class="product-price"><?= money($p['price']) ?></span>
        <?php if (!empty($p['old_price'])): ?>
          <span class="product-old"><?= money($p['old_price']) ?></span>
        <?php endif; ?>
        <?php if (!empty($p['discount'])): ?>
          <span class="product-off"><?= (int)$p['discount'] ?>% OFF</span>
        <?php endif; ?>
      </div>

      <p class="product-desc">
        <?= nl2br(htmlspecialchars($p['description'] ?? '')) ?>
      </p>

      <div class="product-meta">
        <div class="meta-item">
          <strong>Availability:</strong>
          <?php if ($inStock): ?>
            <span class="text-success">
              <i class="fa fa-check-circle"></i>
              In stock (<?= (int)$p['stock'] ?>)
            </span>
          <?php else: ?>
            <span class="text-danger">
              <i class="fa fa-times-circle"></i> Out of stock
            </span>
          <?php endif; ?>
        </div>
        <div class="meta-item">
          <strong>Category:</strong>
          <?= htmlspecialchars($p['cat_name'] ?? '—') ?>
        </div>
        <div class="meta-item">
          <strong>SKU:</strong>
          DM-<?= str_pad((int)$p['id'], 5, '0', STR_PAD_LEFT) ?>
        </div>
      </div>

      <?php if ($inStock): ?>
        <form action="cart/cart_add.php" method="post"
              class="add-to-cart-form mt-3">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

          <div class="qty-wrap">
            <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
            <input type="number" name="qty" id="qtyInput"
                   value="1" min="1" max="<?= (int)$p['stock'] ?>">
            <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
          </div>

          <button type="submit" class="btn btn-brand flex-grow-1">
            <i class="fa fa-cart-plus me-1"></i> Add to Cart
          </button>
        </form>
      <?php else: ?>
        <button class="btn btn-secondary w-100 mt-3" disabled>
          <i class="fa fa-times-circle me-1"></i> Out of Stock
        </button>
      <?php endif; ?>

      <div class="mt-3">
        <a href="cart/cart.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa fa-shopping-cart"></i> Go to Cart
        </a>
        <a href="products.php" class="btn btn-outline-secondary btn-sm ms-2">
          <i class="fa fa-th"></i> Continue Shopping
        </a>
      </div>
    </div>
  </div>

  <!-- TABS: Description / Shipping / Reviews -->
  <div class="product-tabs mt-4">
    <ul class="nav nav-tabs" id="prodTabs" role="tablist">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab"
                data-bs-target="#tab-desc" type="button">Description</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab"
                data-bs-target="#tab-ship" type="button">Shipping</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab"
                data-bs-target="#tab-review" type="button">Reviews</button>
      </li>
    </ul>

    <div class="tab-content bg-white p-3 rounded-bottom">
      <div class="tab-pane fade show active" id="tab-desc">
        <p class="mb-0"><?= nl2br(htmlspecialchars($p['description'] ?? 'No description available.')) ?></p>
      </div>
      <div class="tab-pane fade" id="tab-ship">
        <ul class="mb-0">
          <li>Free delivery inside Kathmandu Valley</li>
          <li>2–4 days delivery for other districts</li>
          <li>Cash on Delivery available</li>
          <li>7-day easy return policy</li>
        </ul>
      </div>
      <div class="tab-pane fade" id="tab-review">
        <p class="text-muted mb-0">No reviews yet. Be the first to review this product.</p>
      </div>
    </div>
  </div>

  <!-- RELATED PRODUCTS -->
  <?php if ($related): ?>
    <div class="section-head mt-5">
      <h4>Related Products</h4>
      <a href="category.php?id=<?= (int)$p['category_id'] ?>">
        View All <i class="fa fa-arrow-right"></i>
      </a>
    </div>

    <div class="row g-3">
      <?php foreach ($related as $r): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <div class="p-card">
            <a href="product.php?id=<?= (int)$r['id'] ?>" class="wish"><i class="fa fa-heart"></i></a>
            <a href="product.php?id=<?= (int)$r['id'] ?>">
              <img src="<?= htmlspecialchars(img($r['image'])) ?>" alt="">
            </a>
            <div class="body">
              <h6><?= htmlspecialchars($r['name']) ?></h6>
              <div>
                <span class="price"><?= money($r['price']) ?></span>
                <?php if (!empty($r['old_price'])): ?>
                  <span class="old"><?= money($r['old_price']) ?></span>
                <?php endif; ?>
                <?php if (!empty($r['discount'])): ?>
                  <span class="off"><?= (int)$r['discount'] ?>% off</span>
                <?php endif; ?>
              </div>
              <form action="cart/cart_add.php" method="post" class="mt-2">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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

</div>

<style>
/* ---------- Product page ---------- */
.product-img-wrap{
  position:relative;
  border-radius:12px;
  overflow:hidden;
  background:#f6f0ff;
  aspect-ratio:1/1;
}
.product-img-wrap img{
  width:100%;height:100%;object-fit:cover;display:block;
}
.p-badge{
  position:absolute;top:14px;left:14px;
  background:#e53935;color:#fff;
  padding:4px 10px;border-radius:6px;
  font-size:.78rem;font-weight:700;
}
.p-wish{
  position:absolute;top:14px;right:14px;
  background:#fff;border:none;border-radius:50%;
  width:38px;height:38px;
  display:flex;align-items:center;justify-content:center;
  color:var(--brand);box-shadow:0 2px 8px rgba(0,0,0,.15);
  cursor:pointer;transition:.2s;
}
.p-wish:hover{background:var(--brand);color:#fff;}

.mini-trust{
  background:#faf7ff;border-radius:8px;
  text-align:center;padding:8px 4px;
  font-size:.72rem;color:#555;font-weight:600;
}
.mini-trust i{color:var(--brand);margin-right:3px;}

.product-title{
  font-weight:800;
  font-size:1.6rem;
  margin:0 0 6px;
  line-height:1.25;
}
.product-cat{
  font-size:.82rem;color:#888;margin-bottom:12px;
}
.product-cat i{color:var(--brand);margin-right:4px;}
.product-cat a{color:#888;text-decoration:none;}
.product-cat a:hover{color:var(--brand);}

.product-price-row{
  display:flex;align-items:center;flex-wrap:wrap;gap:10px;
  margin:12px 0;
}
.product-price{
  color:var(--brand);font-weight:800;font-size:1.9rem;
}
.product-old{
  color:#999;text-decoration:line-through;font-size:1.05rem;
}
.product-off{
  background:#ffe5e5;color:#e53935;
  padding:3px 10px;border-radius:4px;
  font-weight:700;font-size:.82rem;
}

.product-desc{
  color:#666;font-size:.92rem;line-height:1.55;
}

.product-meta{
  border-top:1px dashed #eee;
  border-bottom:1px dashed #eee;
  padding:12px 0;
  margin:12px 0;
}
.meta-item{
  font-size:.85rem;color:#555;padding:3px 0;
}
.meta-item strong{color:#333;margin-right:4px;}

.add-to-cart-form{
  display:flex;gap:10px;align-items:stretch;
}
.qty-wrap{
  display:flex;align-items:center;
  border:1px solid #ddd;border-radius:8px;overflow:hidden;
}
.qty-btn{
  background:#fff;border:none;width:36px;height:44px;
  font-size:1.2rem;color:var(--brand);cursor:pointer;
}
.qty-btn:hover{background:#f6f0ff;}
.qty-wrap input{
  width:52px;border:none;text-align:center;
  font-weight:700;height:44px;
}
.qty-wrap input:focus{outline:none;}

.btn-brand{
  background:var(--brand);color:#fff;font-weight:600;
}
.btn-brand:hover{background:var(--brand-dark);color:#fff;}

.product-tabs .nav-tabs{border-bottom:2px solid var(--brand);}
.product-tabs .nav-link{
  color:#555;font-weight:600;
  border:none;border-bottom:2px solid transparent;
  margin-bottom:-2px;
}
.product-tabs .nav-link.active{
  color:var(--brand);
  border-color:transparent transparent var(--brand) transparent;
  background:transparent;
}

/* Mobile */
@media (max-width:576px){
  .product-title{font-size:1.3rem;}
  .product-price{font-size:1.5rem;}
  .add-to-cart-form{flex-direction:column;}
}
</style>

<script>
function changeQty(delta){
  const input = document.getElementById('qtyInput');
  if (!input) return;
  let v = parseInt(input.value) || 1;
  v += delta;
  const max = parseInt(input.max) || 999;
  if (v < 1) v = 1;
  if (v > max) v = max;
  input.value = v;
}
</script>

<?php require_once 'includes/footer.php'; ?>