<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

/* Must be logged in */
if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = '../checkout.php';
    header('Location: ../login.php?msg=login_required');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['cart'])) {
    header('Location: ../checkout.php');
    exit;
}

/* ---------- Collect POST ---------- */
$name     = trim($_POST['name']     ?? '');
$email    = trim($_POST['email']    ?? '');
$phone    = trim($_POST['phone']    ?? '');
$province = trim($_POST['province'] ?? '');
$city     = trim($_POST['city']     ?? '');
$area     = trim($_POST['area']     ?? '');
$landmark = trim($_POST['landmark'] ?? '');
$address  = trim($_POST['address']  ?? '');
$notes    = trim($_POST['notes']    ?? '');
$pay      = $_POST['payment_method'] ?? 'cod';

if (!in_array($pay, ['cod', 'online'], true)) $pay = 'cod';

/* ---------- Validate required ---------- */
if ($name === '' || $phone === '' || $province === '' || $city === '' || $area === '' || $address === '') {
    die('Missing required fields. <a href="../checkout.php">Go back</a>');
}

/* ---------- Full address string for orders ---------- */
$fullAddress = $address;
if ($area)     $fullAddress .= ', ' . $area;
if ($city)     $fullAddress .= ', ' . $city;
if ($province) $fullAddress .= ', ' . $province;
if ($landmark) $fullAddress .= ' (Near ' . $landmark . ')';

/* ---------- Build items + total ---------- */
$ids = array_keys($_SESSION['cart']);
$in  = implode(',', array_fill(0, count($ids), '?'));

$stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($in)");
$stmt->execute($ids);

$items = [];
$total = 0;
foreach ($stmt->fetchAll() as $r) {
    $qty = (int)$_SESSION['cart'][$r['id']];
    $sub = $r['price'] * $qty;
    $total += $sub;
    $items[] = ['id'=>$r['id'], 'name'=>$r['name'], 'price'=>$r['price'], 'qty'=>$qty];
}

try {
    $pdo->beginTransaction();

    /* ---------- Insert order ---------- */
    $insOrder = $pdo->prepare("
        INSERT INTO orders
            (user_id, customer_name, customer_email, customer_phone,
             address, city, total, payment_method)
        VALUES
            (:uid, :n, :e, :p, :a, :c, :t, :pay)
    ");
    $insOrder->execute([
        ':uid' => $_SESSION['user_id'],
        ':n'   => $name,
        ':e'   => $email,
        ':p'   => $phone,
        ':a'   => $fullAddress,
        ':c'   => $city,
        ':t'   => $total,
        ':pay' => $pay,
    ]);
    $orderId = $pdo->lastInsertId();

    /* ---------- Insert order items ---------- */
    $insItem = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, price, qty)
        VALUES (:o, :pid, :pn, :pr, :q)
    ");
    foreach ($items as $it) {
        $insItem->execute([
            ':o'   => $orderId,
            ':pid' => $it['id'],
            ':pn'  => $it['name'],
            ':pr'  => $it['price'],
            ':q'   => $it['qty'],
        ]);
    }

    /* ---------- ALWAYS save latest shipping details ---------- */
    $upd = $pdo->prepare("
        UPDATE users
        SET name = :n, phone = :p, province = :pr,
            city = :c, area = :ar, address = :a, notes = :no
        WHERE id = :uid
    ");
    $upd->execute([
        ':n'  => $name,
        ':p'  => $phone,
        ':pr' => $province,
        ':c'  => $city,
        ':ar' => $area,
        ':a'  => $address,
        ':no' => $notes,
        ':uid'=> $_SESSION['user_id'],
    ]);
    $_SESSION['user_name'] = $name;

    $pdo->commit();

    $_SESSION['cart'] = [];

    header('Location: order_success.php?id=' . $orderId);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die('Order failed: ' . $e->getMessage());
}