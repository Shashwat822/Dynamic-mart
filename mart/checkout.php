<?php
require_once 'includes/header.php';

/* ---------- Must be logged in ---------- */
if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = 'checkout.php';
    header('Location: login.php?msg=login_required');
    exit;
}

/* ---------- Cart must not be empty ---------- */
if (empty($_SESSION['cart'])) {
    echo '<div class="container my-5"><div class="alert alert-warning">
          Your cart is empty. <a href="products.php">Shop now</a>.</div></div>';
    require_once 'includes/footer.php';
    exit;
}

/* ---------- Load user's saved details (incl. address) ---------- */
$ustmt = $pdo->prepare("
    SELECT name, email, phone, province, city, area, address, notes
    FROM users
    WHERE id = :id
");
$ustmt->execute([':id' => $_SESSION['user_id']]);
$user = $ustmt->fetch() ?: [];

/* ---------- Build cart summary ---------- */
$items = [];
$total = 0;
$ids   = array_keys($_SESSION['cart']);
$in    = implode(',', array_fill(0, count($ids), '?'));

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
?>

<div class="container my-4">
  <div class="section-head">
    <h4><i class="fa fa-credit-card me-2"></i> Checkout</h4>
    <a href="cart/cart.php"><i class="fa fa-arrow-left"></i> Back to Cart</a>
  </div>

  <form action="cart/order_save.php" method="post" id="checkoutForm">
    <div class="row g-3">

      <!-- ============ LEFT: Delivery + Payment ============ -->
      <div class="col-lg-7">

        <!-- Contact details -->
        <div class="bg-white rounded shadow-sm p-4">
          <h5 style="font-weight:800;color:var(--brand);">
            <i class="fa fa-user me-2"></i> Contact Details
          </h5>
          <hr>

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small text-muted">Full Name *</label>
              <input type="text" name="name" class="form-control" required
                     value="<?= htmlspecialchars($user['name'] ?? $_SESSION['user_name'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small text-muted">Email</label>
              <input type="email" name="email" class="form-control"
                     value="<?= htmlspecialchars($user['email'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small text-muted">Phone *</label>
              <input type="text" name="phone" class="form-control" required
                     placeholder="98XXXXXXXX"
                     value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
            </div>
          </div>
        </div>

        <!-- Delivery address -->
        <div class="bg-white rounded shadow-sm p-4 mt-3">
          <h5 style="font-weight:800;color:var(--brand);">
            <i class="fa fa-truck me-2"></i> Delivery Address
          </h5>
          <hr>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small text-muted">Province *</label>
              <select name="province" class="form-select" required>
                <option value="">Choose…</option>
                <?php
                $provinces = [
                  'Koshi', 'Madhesh', 'Bagmati', 'Gandaki',
                  'Lumbini', 'Karnali', 'Sudurpashchim'
                ];
                $cur = $user['province'] ?? '';
                foreach ($provinces as $p) {
                    $sel = ($cur === $p) ? 'selected' : '';
                    echo "<option value=\"$p\" $sel>$p</option>";
                }
                ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small text-muted">City / District *</label>
              <input type="text" name="city" class="form-control" required
                     placeholder="e.g. Kathmandu"
                     value="<?= htmlspecialchars($user['city'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small text-muted">Area / Ward *</label>
              <input type="text" name="area" class="form-control" required
                     placeholder="e.g. Baneshwor, Ward 10"
                     value="<?= htmlspecialchars($user['area'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label small text-muted">Landmark (optional)</label>
              <input type="text" name="landmark" class="form-control"
                     placeholder="e.g. near Big Mart">
            </div>

            <div class="col-12">
              <label class="form-label small text-muted">Full Address *</label>
              <textarea name="address" class="form-control" rows="2" required
                        placeholder="House no, street, tole…"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
            </div>

            <div class="col-12">
              <label class="form-label small text-muted">Delivery Notes (optional)</label>
              <input type="text" name="notes" class="form-control"
                     placeholder="e.g. Call before delivery"
                     value="<?= htmlspecialchars($user['notes'] ?? '') ?>">
            </div>

            <div class="col-12">
              <div class="alert alert-light border small mb-0 py-2">
                <i class="fa fa-info-circle me-1" style="color:var(--brand);"></i>
                Your delivery details are saved automatically for faster checkout next time.
              </div>
            </div>
          </div>
        </div>

        <!-- Payment method -->
        <div class="bg-white rounded shadow-sm p-4 mt-3">
          <h5 style="font-weight:800;color:var(--brand);">
            <i class="fa fa-wallet me-2"></i> Payment Method
          </h5>
          <hr>

          <div class="form-check mb-3 p-3 border rounded" style="cursor:pointer;">
            <input class="form-check-input" type="radio" name="payment_method"
                   id="payCod" value="cod" checked>
            <label class="form-check-label w-100" for="payCod" style="cursor:pointer;">
              <strong><i class="fa fa-money-bill-wave me-2"></i> Cash on Delivery</strong>
              <div class="small text-muted">Pay when your order arrives at your door.</div>
            </label>
          </div>

          <div class="form-check p-3 border rounded" style="cursor:pointer;">
            <input class="form-check-input" type="radio" name="payment_method"
                   id="payOnline" value="online">
            <label class="form-check-label w-100" for="payOnline" style="cursor:pointer;">
              <strong><i class="fa fa-globe me-2"></i> Online Payment</strong>
              <div class="small text-muted">Pay via card / wallet (coming soon).</div>
            </label>
          </div>
        </div>
      </div>

      <!-- ============ RIGHT: Order summary ============ -->
      <div class="col-lg-5">
        <div class="bg-white rounded shadow-sm p-4" style="position:sticky;top:20px;">
          <h5 style="font-weight:800;color:var(--brand);">Order Summary</h5>
          <hr>

          <?php foreach ($items as $it): ?>
            <div class="d-flex align-items-center gap-2 mb-3">
              <img src="<?= htmlspecialchars(img($it['image'])) ?>"
                   style="width:44px;height:44px;object-fit:cover;border-radius:6px;">
              <div class="flex-grow-1 min-w-0">
                <div class="small fw-semibold text-truncate"><?= htmlspecialchars($it['name']) ?></div>
                <div class="small text-muted">× <?= (int)$it['qty'] ?></div>
              </div>
              <div class="small fw-bold" style="color:var(--brand);"><?= money($it['sub']) ?></div>
            </div>
          <?php endforeach; ?>

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

          <button class="btn w-100 text-white" style="background:var(--brand);">
            <i class="fa fa-check-circle me-1"></i> Place Order
          </button>

          <div class="text-center small text-muted mt-2">
            <i class="fa fa-lock me-1"></i> Secure checkout
          </div>
        </div>
      </div>

    </div>
  </form>
</div>

<style>
@media (max-width:992px){
  .col-lg-5 .bg-white{position:static !important;}
}
</style>

<?php require_once 'includes/footer.php'; ?>