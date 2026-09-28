<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

/* Must be logged in as seller */
if (empty($_SESSION['seller_id'])) {
    $_SESSION['redirect_after_seller_login'] = 'dashboard.php';
    header('Location: login.php?msg=login_required');
    exit;
}

/* Fetch seller + check status */
$sid = (int)$_SESSION['seller_id'];
$s = $pdo->prepare("SELECT * FROM sellers WHERE id = :id");
$s->execute([':id' => $sid]);
$seller = $s->fetch();

if (!$seller) {
    unset($_SESSION['seller_id'], $_SESSION['seller_name']);
    header('Location: login.php');
    exit;
}

/* Not active → block access */
if ($seller['status'] !== 'active') {
    unset($_SESSION['seller_id'], $_SESSION['seller_name'], $_SESSION['seller_email']);
    $_SESSION['seller_pending_email'] = $seller['owner_email'];
    if ($seller['status'] === 'pending') {
        header('Location: pending.php');
    } else {
        header('Location: login.php?msg=rejected');
    }
    exit;
}

$sellerId = $sid;

/* … rest of your existing dashboard.php stays the same … */