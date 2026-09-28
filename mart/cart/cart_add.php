<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

/* Not logged in → send to login, come back later */
if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'] ?? '../index.php';
    header('Location: ../login.php?msg=login_required');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$id  = (int)($_POST['id']  ?? 0);
$qty = max(1, (int)($_POST['qty'] ?? 1));

if ($id <= 0) {
    header('Location: ../index.php');
    exit;
}

/* Product must exist */
$stmt = $pdo->prepare("SELECT id FROM products WHERE id = :id");
$stmt->execute([':id' => $id]);
if (!$stmt->fetch()) {
    header('Location: ../index.php');
    exit;
}

/* Add to session cart */
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
$_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;

/* ---------- Safe redirect: only allow going back to files in the site ---------- */
$back = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';

if ($back && strpos($back, $host) !== false) {
    /* Same host — trust it */
    header('Location: ' . $back);
} else {
    /* Fallback: homepage */
    header('Location: ../index.php');
}
exit;