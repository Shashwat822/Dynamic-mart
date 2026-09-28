<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$set = $_GET['set'] ?? 'en';
if (!in_array($set, ['en', 'np'], true)) $set = 'en';

$_SESSION['lang'] = $set;

/* Go back to where the user came from */
$back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $back);
exit;