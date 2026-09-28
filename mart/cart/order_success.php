<?php
require_once __DIR__ . '/../includes/header.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$oid = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id");
$stmt->execute([':id' => $oid]);
$order = $stmt->fetch();

$items = [];
if ($order) {
    $s = $pdo->prepare("SELECT * FROM order_items WHERE order_id = :id");
    $s->execute([':id' => $oid]);
    $items = $s->fetchAll();
}
?>

<div class="container my-5" style="max-width:720px;">
  <?php if (!$order): ?>
    <div class="alert alert-danger">Order not found.</div>
  <?php else: ?>
    <div class="bg-white rounded shadow-sm p-4 text-center">
      <i class="fa fa-check-circle" style="font-size:4rem;color:#2e7d32;"></i>
      <h3 class="mt-3" style="font-weight:800;color:var(--brand);">Order Placed Successfully!</h3>
      <p class="text-muted">Thank you, <?= htmlspecialchars($order['customer_name']) ?>.</p>

      <div class="text-start mt-4">
        <p><strong>Order #:</strong> <?= (int)$order['id'] ?></p>
        <p><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
        <p><strong>Address:</strong> <?= htmlspecialchars($order['address']) ?>,
           <?= htmlspecialchars($order['city']) ?></p>
        <p><strong>Payment:</strong> <?= htmlspecialchars(strtoupper($order['payment_method'])) ?></p>
        <hr>
        <table class="table">
          <thead>
            <tr><th>Product</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td><?= htmlspecialchars($it['product_name']) ?></td>
                <td class="text-center"><?= (int)$it['qty'] ?></td>
                <td class="text-end"><?= money($it['price'] * $it['qty']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="2" class="text-end">Total</th>
              <th class="text-end" style="color:var(--brand);"><?= money($order['total']) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <a href="../products.php" class="btn text-white mt-3" style="background:var(--brand);">
        Continue Shopping
      </a>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>