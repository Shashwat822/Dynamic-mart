<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id']  ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));

    if ($id > 0 && isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id] = $qty;
    }
}

header('Location: cart.php');
exit;