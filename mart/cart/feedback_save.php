<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$rating  = (int)($_POST['rating'] ?? 5);
$message = trim($_POST['message'] ?? '');

if ($orderId <= 0 || $rating < 1 || $rating > 5 || $message === '') {
    header('Location: cart.php');
    exit;
}

/* Only allow feedback for the user's own delivered order */
$chk = $pdo->prepare("
    SELECT id FROM orders
    WHERE id = :oid AND user_id = :uid AND status = 'delivered'
");
$chk->execute([':oid' => $orderId, ':uid' => $_SESSION['user_id']]);
if (!$chk->fetch()) {
    header('Location: cart.php');
    exit;
}

$ins = $pdo->prepare("
    INSERT INTO feedback (order_id, user_id, rating, message)
    VALUES (:o, :u, :r, :m)
");
$ins->execute([
    ':o' => $orderId,
    ':u' => $_SESSION['user_id'],
    ':r' => $rating,
    ':m' => $message,
]);

header('Location: cart.php');
exit;