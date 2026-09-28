<?php
require_once __DIR__ . '/../includes/header.php';

/* Must be logged in */
if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = '../cart/cart.php';
    header('Location: ../login.php?msg=login_required');
    exit;
}

$userId = (int)$_SESSION['user_id'];

/* ============================================================
   CART
   ============================================================ */
$items = [];
$total = 0;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("SELECT id, name, price, image FROM products WHERE id IN ($in)");
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $r) {
        $qty = (int)$_SESSION['cart'][$r['id']];
        $sub = $r['price'] * $qty;
        $total += $sub;
        $items[] = [
            'id'    => $r['id'],
            'name'  => $r['name'],
            'price' => $r['price'],
            'image' => $r['image'],
            'qty'   => $qty,
            'sub'   => $sub,
        ];
    }
}

/* ============================================================
   MY ORDERS (with live tracking + delivery address)
   ============================================================ */
$orders = $pdo->prepare("
    SELECT id, total, payment_method, status, created_at,
           tracking_note, shipped_at, delivered_at,
           address, city, customer_phone, customer_email
    FROM orders
    WHERE user_id = :uid
    ORDER BY id DESC
    LIMIT 20
");
$orders->execute([':uid' => $userId]);
$orders = $orders->fetchAll();

/* Fetch items for each order in one query */
$orderItems = [];
if ($orders) {
    $oids = array_column($orders, 'id');
    $in   = implode(',', array_fill(0, count($oids), '?'));

    $s = $pdo->prepare("
        SELECT oi.order_id, oi.product_id, oi.product_name, oi.price, oi.qty,
               p.image, p.description,
               c.name AS cat_name, c.icon AS cat_icon
        FROM order_items oi
        LEFT JOIN products   p ON p.id = oi.product_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE oi.order_id IN ($in)
    ");
    $s->execute($oids);

    foreach ($s->fetchAll() as $row) {
        $orderItems[$row['order_id']][] = $row;
    }
}

/* Feedback given */
$givenFeedback = [];
$fb = $pdo->prepare("SELECT order_id FROM feedback WHERE user_id = :uid");
$fb->execute([':uid' => $userId]);
foreach ($fb->fetchAll() as $r) {
    $givenFeedback[(int)$r['order_id']] = true;
}

/* Status helper */
$stages = [
    'pending'    => 'Order Placed',
    'processing' => 'Processing',
    'shipped'    => 'Shipped',
    'delivered'  => 'Delivered',
];
function stageIndex($status, $stages) {
    $keys = array_keys($stages);
    $i = array_search($status, $keys, true);
    return $i === false ? 0 : $i;
}
?>

<div class="container my-4">

  <!-- ============ TABS ============ -->
  <ul class="nav nav-pills mb-3" id="accTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-cart-btn" data-bs-toggle="pill"
              data-bs-target="#tab-cart" type="button" role="tab">
        <i class="fa fa-shopping-cart me-1"></i> Cart
        <?php if ($items): ?><span class="badge bg-warning text-dark"><?= count($items) ?></span><?php endif; ?>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-orders-btn" data-bs-toggle="pill"
              data-bs-target="#tab-orders" type="button" role="tab">
        <i class="fa fa-box me-1"></i> My Orders
        <?php if ($orders): ?><span class="badge bg-light text-dark"><?= count($orders) ?></span><?php endif; ?>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-support-btn" data-bs-toggle="pill"
              data-bs-target="#tab-support" type="button" role="tab">
        <i class="fa fa-headset me-1"></i> Support
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ============ TAB: CART ============ -->
    <div class="tab-pane fade show active" id="tab-cart" role="tabpanel">
      <div class="section-head">
        <h4><i class="fa fa-shopping-cart me-2"></i> Shopping Cart</h4>
        <a href="../products.php">Continue Shopping <i class="fa fa-arrow-right"></i></a>
      </div>

      <?php if (!$items): ?>
        <div class="alert alert-info">
          Your cart is empty. <a href="../products.php">Start shopping</a>.
        </div>
      <?php else: ?>
        <div class="row g-3">
          <div class="col-lg-8">
            <div class="bg-white rounded shadow-sm p-3">
              <table class="table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th class="text-center" style="width:130px;">Qty</th>
                    <th class="text-end" style="width:120px;">Price</th>
                    <th class="text-end" style="width:120px;">Subtotal</th>
                    <th style="width:50px;"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $it): ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-center gap-3">
                          <img src="<?= htmlspecialchars(img($it['image'])) ?>"
                               style="width:60px;height:60px;object-fit:cover;border-radius:6px;">
                          <a href="../product.php?id=<?= (int)$it['id'] ?>"
                             style="color:#333;text-decoration:none;font-weight:600;">
                            <?= htmlspecialchars($it['name']) ?>
                          </a>
                        </div>
                      </td>
                      <td class="text-center">
                        <form action="cart_update.php" method="post" class="d-flex align-items-center justify-content-center gap-1">
                          <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                          <input type="number" name="qty" value="<?= (int)$it['qty'] ?>" min="1"
                                 class="form-control form-control-sm" style="width:70px;">
                          <button class="btn btn-sm btn-outline-secondary"><i class="fa fa-sync"></i></button>
                        </form>
                      </td>
                      <td class="text-end"><?= money($it['price']) ?></td>
                      <td class="text-end fw-bold" style="color:var(--brand);"><?= money($it['sub']) ?></td>
                      <td class="text-end">
                        <form action="cart_remove.php" method="post">
                          <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                          <button class="btn btn-sm btn-outline-danger remove-item">
                            <i class="fa fa-trash"></i>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="col-lg-4">
            <div class="bg-white rounded shadow-sm p-3">
              <h5 style="font-weight:800;color:var(--brand);">Order Summary</h5>
              <hr>
              <div class="d-flex justify-content-between mb-2">
                <span>Subtotal</span><strong><?= money($total) ?></strong>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span>Delivery</span><strong>Free</strong>
              </div>
              <hr>
              <div class="d-flex justify-content-between mb-3">
                <span>Total</span>
                <strong style="color:var(--brand);font-size:1.2rem;"><?= money($total) ?></strong>
              </div>
              <a href="../checkout.php" class="btn w-100 text-white" style="background:var(--brand);">
                <i class="fa fa-credit-card me-1"></i> Proceed to Checkout
              </a>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- ============ TAB: ORDERS ============ -->
    <div class="tab-pane fade" id="tab-orders" role="tabpanel">
      <div class="section-head">
        <h4><i class="fa fa-box me-2"></i> My Orders</h4>
        <a href="../products.php">Shop More <i class="fa fa-arrow-right"></i></a>
      </div>

      <?php if (!$orders): ?>
        <div class="alert alert-info">
          You haven't placed any orders yet. <a href="../products.php">Start shopping</a>.
        </div>
      <?php else: ?>

        <?php foreach ($orders as $o):
            $idx = stageIndex($o['status'], $stages);
            $itemsList = $orderItems[$o['id']] ?? [];
        ?>
          <div class="order-card mb-3">
            <div class="order-head">
              <div>
                <div class="order-id">Order #<?= (int)$o['id'] ?></div>
                <div class="order-date">
                  <i class="fa fa-clock me-1"></i>
                  <?= date('d M Y, h:i A', strtotime($o['created_at'])) ?>
                </div>
                <?php if (!empty($o['address'])): ?>
                  <div class="order-ship">
                    <i class="fa fa-truck me-1"></i>
                    <?= htmlspecialchars(mb_substr($o['address'], 0, 60)) ?><?= mb_strlen($o['address']) > 60 ? '…' : '' ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="text-end">
                <div class="order-total"><?= money($o['total']) ?></div>
                <div class="order-pay">
                  <i class="fa <?= $o['payment_method'] === 'cod' ? 'fa-money-bill-wave' : 'fa-globe' ?> me-1"></i>
                  <?= strtoupper($o['payment_method']) ?>
                </div>
              </div>
            </div>

            <!-- Live tracking bar -->
            <div class="tracker">
              <?php $i = 0; foreach ($stages as $key => $label): ?>
                <div class="track-step <?= $i <= $idx ? 'done' : '' ?> <?= $i == $idx ? 'current' : '' ?>">
                  <div class="dot">
                    <i class="fa <?=
                      $key === 'pending'    ? 'fa-receipt' :
                      ($key === 'processing' ? 'fa-cog'   :
                      ($key === 'shipped'    ? 'fa-truck' : 'fa-check'))
                    ?>"></i>
                  </div>
                  <div class="label"><?= $label ?></div>
                </div>
                <?php if ($i < count($stages) - 1): ?>
                  <div class="track-line <?= $i < $idx ? 'done' : '' ?>"></div>
                <?php endif; ?>
              <?php $i++; endforeach; ?>
            </div>

            <?php if (!empty($o['tracking_note'])): ?>
              <div class="tracking-note">
                <i class="fa fa-info-circle me-1"></i><?= htmlspecialchars($o['tracking_note']) ?>
              </div>
            <?php endif; ?>

            <!-- Items with full product details -->
            <div class="order-items">
              <?php foreach ($itemsList as $it): ?>
                <div class="order-item-full">
                  <a href="../product.php?id=<?= (int)$it['product_id'] ?>" class="oi-thumb">
                    <img src="<?= htmlspecialchars(img($it['image'] ?? '')) ?>" alt="">
                  </a>
                  <div class="oi-info">
                    <a href="../product.php?id=<?= (int)$it['product_id'] ?>" class="oi-name">
                      <?= htmlspecialchars($it['product_name']) ?>
                    </a>
                    <?php if (!empty($it['cat_name'])): ?>
                      <div class="oi-cat">
                        <i class="fa <?= htmlspecialchars($it['cat_icon'] ?: 'fa-tag') ?>"></i>
                        <?= htmlspecialchars($it['cat_name']) ?>
                      </div>
                    <?php endif; ?>
                    <?php if (!empty($it['description'])): ?>
                      <div class="oi-desc">
                        <?= htmlspecialchars(mb_substr($it['description'], 0, 90)) ?><?= mb_strlen($it['description']) > 90 ? '…' : '' ?>
                      </div>
                    <?php endif; ?>
                    <div class="oi-meta">
                      <span class="oi-meta-item"><i class="fa fa-tag"></i> <?= money($it['price']) ?> each</span>
                      <span class="oi-meta-item"><i class="fa fa-cubes"></i> Qty <?= (int)$it['qty'] ?></span>
                    </div>
                  </div>
                  <div class="oi-total">
                    <div class="oi-total-value"><?= money($it['price'] * $it['qty']) ?></div>
                    <div class="oi-total-label">Line total</div>
                  </div>
                </div>
              <?php endforeach; ?>

              <div class="oi-summary">
                <div class="oi-sum-row">
                  <span>Subtotal</span><strong><?= money($o['total']) ?></strong>
                </div>
                <div class="oi-sum-row">
                  <span>Delivery</span><strong>Free</strong>
                </div>
                <div class="oi-sum-row grand">
                  <span>Total Paid</span><strong><?= money($o['total']) ?></strong>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="order-actions">
              <button class="btn btn-sm btn-outline-secondary"
                      data-bs-toggle="collapse"
                      data-bs-target="#details<?= (int)$o['id'] ?>">
                <i class="fa fa-info-circle me-1"></i> Details
              </button>

              <?php if ($o['status'] === 'delivered' && empty($givenFeedback[$o['id']])): ?>
                <button class="btn btn-sm text-white ms-2"
                        style="background:var(--brand);"
                        data-bs-toggle="modal"
                        data-bs-target="#fbModal<?= (int)$o['id'] ?>">
                  <i class="fa fa-star me-1"></i> Give Feedback
                </button>
              <?php elseif (!empty($givenFeedback[$o['id']])): ?>
                <span class="badge bg-success ms-2">
                  <i class="fa fa-check me-1"></i> Feedback given
                </span>
              <?php endif; ?>

              <a href="../page.php?slug=contact" class="btn btn-sm btn-outline-secondary ms-2">
                <i class="fa fa-headset me-1"></i> Support
              </a>
            </div>

            <!-- Collapsible details -->
            <div class="collapse mt-3" id="details<?= (int)$o['id'] ?>">
              <div class="order-detail">
                <div class="row g-3">

                  <div class="col-md-6">
                    <div class="detail-label">Payment Method</div>
                    <div class="detail-value">
                      <?= $o['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Online Payment' ?>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="detail-label">Status</div>
                    <div class="detail-value text-capitalize"><?= htmlspecialchars($o['status']) ?></div>
                  </div>

                  <?php if (!empty($o['address'])): ?>
                    <div class="col-12">
                      <div class="detail-label">Delivery Address</div>
                      <div class="detail-value">
                        <i class="fa fa-map-marker-alt me-1" style="color:var(--brand);"></i>
                        <?= htmlspecialchars($o['address']) ?>
                        <?php if (!empty($o['city'])): ?>, <?= htmlspecialchars($o['city']) ?><?php endif; ?>
                      </div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($o['customer_phone'])): ?>
                    <div class="col-md-6">
                      <div class="detail-label">Contact Phone</div>
                      <div class="detail-value">
                        <i class="fa fa-phone me-1" style="color:var(--brand);"></i>
                        <?= htmlspecialchars($o['customer_phone']) ?>
                      </div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($o['customer_email'])): ?>
                    <div class="col-md-6">
                      <div class="detail-label">Contact Email</div>
                      <div class="detail-value">
                        <i class="fa fa-envelope me-1" style="color:var(--brand);"></i>
                        <?= htmlspecialchars($o['customer_email']) ?>
                      </div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($o['shipped_at'])): ?>
                    <div class="col-md-6">
                      <div class="detail-label">Shipped On</div>
                      <div class="detail-value"><?= date('d M Y, h:i A', strtotime($o['shipped_at'])) ?></div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($o['delivered_at'])): ?>
                    <div class="col-md-6">
                      <div class="detail-label">Delivered On</div>
                      <div class="detail-value"><?= date('d M Y, h:i A', strtotime($o['delivered_at'])) ?></div>
                    </div>
                  <?php endif; ?>

                </div>
              </div>
            </div>
          </div>

          <!-- Feedback modal -->
          <?php if ($o['status'] === 'delivered' && empty($givenFeedback[$o['id']])): ?>
            <div class="modal fade" id="fbModal<?= (int)$o['id'] ?>" tabindex="-1">
              <div class="modal-dialog">
                <form method="post" action="feedback_save.php" class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Feedback for Order #<?= (int)$o['id'] ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                    <div class="mb-2">
                      <label class="form-label small text-muted">Rating</label>
                      <select name="rating" class="form-select" required>
                        <option value="5">★★★★★ — Excellent</option>
                        <option value="4">★★★★ — Good</option>
                        <option value="3">★★★ — Okay</option>
                        <option value="2">★★ — Poor</option>
                        <option value="1">★ — Bad</option>
                      </select>
                    </div>
                    <div class="mb-2">
                      <label class="form-label small text-muted">Message</label>
                      <textarea name="message" class="form-control" rows="3" required></textarea>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button class="btn text-white" style="background:var(--brand);">Submit</button>
                  </div>
                </form>
              </div>
            </div>
          <?php endif; ?>

        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ============ TAB: SUPPORT ============ -->
    <div class="tab-pane fade" id="tab-support" role="tabpanel">
      <div class="section-head">
        <h4><i class="fa fa-headset me-2"></i> Support Center</h4>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="support-card">
            <i class="fa fa-envelope"></i>
            <h6>Email Us</h6>
            <p>support@dynamicmart.com</p>
            <a href="mailto:support@dynamicmart.com" class="btn btn-sm text-white" style="background:var(--brand);">Send Email</a>
          </div>
        </div>
        <div class="col-md-6">
          <div class="support-card">
            <i class="fa fa-phone"></i>
            <h6>Call Us</h6>
            <p>+977-1-XXXXXXX</p>
            <a href="tel:+9771XXXXXXX" class="btn btn-sm text-white" style="background:var(--brand);">Call Now</a>
          </div>
        </div>
        <div class="col-md-6">
          <div class="support-card">
            <i class="fa fa-undo"></i>
            <h6>Returns &amp; Refunds</h6>
            <p>7-day easy return policy</p>
            <a href="../page.php?slug=returns" class="btn btn-sm text-white" style="background:var(--brand);">Learn More</a>
          </div>
        </div>
        <div class="col-md-6">
          <div class="support-card">
            <i class="fa fa-truck"></i>
            <h6>Shipping Info</h6>
            <p>Free inside Kathmandu Valley</p>
            <a href="../page.php?slug=shipping" class="btn btn-sm text-white" style="background:var(--brand);">Learn More</a>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<style>
#accTabs .nav-link{
  color:#333;background:#fff;border-radius:8px;margin-right:8px;
  font-weight:600;box-shadow:0 2px 6px rgba(0,0,0,.05);
}
#accTabs .nav-link.active{background:var(--brand);color:#fff;}
#accTabs .nav-link .badge{margin-left:6px;}

.order-card{
  background:#fff;border-radius:10px;padding:16px 18px;
  box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.order-head{
  display:flex;justify-content:space-between;align-items:center;
  padding-bottom:12px;border-bottom:1px solid #f0f0f0;
}
.order-id{font-weight:800;font-size:1rem;color:var(--brand);}
.order-date{font-size:.78rem;color:#999;}
.order-ship{font-size:.74rem;color:#888;margin-top:2px;}
.order-ship i{color:var(--brand);margin-right:3px;}
.order-total{font-weight:800;font-size:1.05rem;}
.order-pay{font-size:.78rem;color:#777;}

.tracker{
  display:flex;align-items:center;justify-content:space-between;
  padding:18px 0 8px;gap:6px;
}
.track-step{
  display:flex;flex-direction:column;align-items:center;gap:6px;
  text-align:center;flex:1;
}
.track-step .dot{
  width:38px;height:38px;border-radius:50%;
  background:#eee;color:#999;
  display:flex;align-items:center;justify-content:center;
  font-size:.95rem;transition:.2s;
}
.track-step.done .dot{background:var(--brand);color:#fff;}
.track-step.current .dot{
  background:var(--brand);color:#fff;
  box-shadow:0 0 0 6px rgba(91,43,140,.18);
}
.track-step .label{font-size:.72rem;color:#777;font-weight:600;}
.track-step.done .label{color:var(--brand);}

.track-line{
  flex:0.6;height:3px;background:#eee;border-radius:2px;margin:0 -4px;
}
.track-line.done{background:var(--brand);}

.tracking-note{
  background:#f6f0ff;color:var(--brand);
  padding:8px 12px;border-radius:6px;font-size:.82rem;margin-top:6px;
}

.order-items{
  margin-top:14px;
  border-top:1px dashed #eee;
  padding-top:10px;
}
.order-item-full{
  display:flex;gap:14px;align-items:center;
  padding:12px 0;
  border-bottom:1px dashed #eee;
}
.order-item-full:last-child{border-bottom:none;}

.oi-thumb{
  flex-shrink:0;display:block;
  width:70px;height:70px;
  border-radius:8px;overflow:hidden;
  background:#f6f0ff;
}
.oi-thumb img{width:100%;height:100%;object-fit:cover;display:block;}

.oi-info{flex:1;min-width:0;}
.oi-name{
  color:#333;font-weight:700;font-size:.95rem;
  text-decoration:none;display:block;margin-bottom:2px;
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.oi-name:hover{color:var(--brand);}
.oi-cat{font-size:.74rem;color:#888;margin-bottom:3px;}
.oi-cat i{color:var(--brand);margin-right:4px;}
.oi-desc{font-size:.76rem;color:#999;margin-bottom:5px;line-height:1.3;}
.oi-meta{display:flex;flex-wrap:wrap;gap:12px;}
.oi-meta-item{font-size:.76rem;color:#666;}
.oi-meta-item i{color:var(--brand);margin-right:4px;}

.oi-total{flex-shrink:0;text-align:right;min-width:110px;}
.oi-total-value{color:var(--brand);font-weight:800;font-size:1rem;}
.oi-total-label{font-size:.7rem;color:#aaa;text-transform:uppercase;letter-spacing:.4px;}

.oi-summary{
  margin-top:12px;background:#faf7ff;border-radius:8px;
  padding:12px 14px;
}
.oi-sum-row{
  display:flex;justify-content:space-between;
  font-size:.88rem;padding:3px 0;color:#555;
}
.oi-sum-row.grand{
  border-top:1px solid #e6dcf5;margin-top:6px;
  padding-top:8px;font-size:1rem;color:#333;
}
.oi-sum-row.grand strong{color:var(--brand);font-size:1.1rem;}

.order-actions{margin-top:12px;}
.order-detail{background:#faf7ff;border-radius:8px;padding:14px;}
.detail-label{font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;}
.detail-value{font-weight:600;font-size:.9rem;}

.support-card{
  background:#fff;border-radius:10px;padding:22px;text-align:center;
  box-shadow:0 2px 8px rgba(0,0,0,.05);height:100%;transition:.2s;
}
.support-card:hover{transform:translateY(-3px);box-shadow:0 6px 14px rgba(0,0,0,.1);}
.support-card i{
  font-size:1.8rem;color:var(--brand);background:#f6f0ff;
  border-radius:50%;width:60px;height:60px;
  display:inline-flex;align-items:center;justify-content:center;
  margin-bottom:10px;
}
.support-card h6{font-weight:700;margin-bottom:4px;}
.support-card p{font-size:.82rem;color:#777;margin-bottom:12px;}

@media (max-width:576px){
  .order-item-full{flex-wrap:wrap;}
  .oi-total{text-align:left;min-width:0;width:100%;margin-top:6px;}
  .oi-total-label{display:none;}
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>