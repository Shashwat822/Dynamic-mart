<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* Prevent double include */
if (defined('ADMIN_HEADER_LOADED')) return;
define('ADMIN_HEADER_LOADED', true);

/* DB (one level up) */
require_once __DIR__ . '/../db.php';

/* Must be logged in as admin */
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php?msg=login_required');
    exit;
}

/* Fresh admin info */
$a = $pdo->prepare("SELECT id, name, email, role FROM admins WHERE id = :id");
$a->execute([':id' => $_SESSION['admin_id']]);
$admin = $a->fetch();

if (!$admin) {
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['admin_email']);
    header('Location: login.php');
    exit;
}

$adminName = $admin['name'];
$adminRole = $admin['role'];
$adminMail = $admin['email'];

/* Current page for sidebar highlight */
$current = basename($_SERVER['PHP_SELF']);

/* Pending sellers badge */
$pendingCount = 0;
try {
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM sellers WHERE status='pending'")->fetchColumn();
} catch (Exception $e) { $pendingCount = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin · Dynamic Mart</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{
  --admin:#1f3a68;
  --admin-dark:#162d52;
  --admin-accent:#ff6a00;
  --admin-bg:#f0f2f6;
}
body{
  background:var(--admin-bg);
  font-family:system-ui,'Segoe UI',Roboto,'Noto Sans Devanagari',sans-serif;
  padding-top:64px;
}

/* ---------- TOP BAR ---------- */
.admin-topbar{
  background:var(--admin);
  color:#fff;
  position:fixed;
  top:0;left:0;right:0;
  height:64px;
  z-index:1040;
  box-shadow:0 2px 8px rgba(0,0,0,.15);
}
.admin-topbar .logo{
  display:flex;align-items:center;gap:10px;
  color:#fff;text-decoration:none;
  font-weight:800;font-size:1.15rem;
}
.admin-topbar .logo i{
  background:#fff;color:var(--admin);
  border-radius:6px;padding:5px 8px;
}
.admin-topbar .role-badge{
  background:rgba(255,255,255,.15);
  font-size:.7rem;
  padding:3px 8px;
  border-radius:12px;
  text-transform:uppercase;
  letter-spacing:.5px;
}
.admin-topbar a{color:#fff;text-decoration:none;}
.admin-topbar a:hover{opacity:.85;}
.admin-topbar .user-chip{
  display:flex;align-items:center;gap:8px;
  background:rgba(255,255,255,.12);
  padding:6px 12px;border-radius:24px;
  font-size:.85rem;
}
.admin-topbar .user-chip .avatar{
  width:26px;height:26px;border-radius:50%;
  background:#fff;color:var(--admin);
  display:flex;align-items:center;justify-content:center;
  font-weight:800;font-size:.75rem;
}

/* ---------- SIDEBAR ---------- */
.admin-sidebar{
  position:fixed;
  top:64px;left:0;bottom:0;
  width:240px;
  background:#fff;
  border-right:1px solid #e3e6ee;
  overflow-y:auto;
  z-index:1030;
  transition:transform .25s;
}
.admin-sidebar .section-label{
  padding:16px 20px 6px;
  font-size:.7rem;
  color:#8a93a6;
  text-transform:uppercase;
  letter-spacing:.7px;
  font-weight:700;
}
.admin-sidebar a{
  display:flex;align-items:center;gap:10px;
  padding:11px 20px;
  color:#2b3550;
  text-decoration:none;
  font-size:.9rem;
  font-weight:500;
  border-left:3px solid transparent;
  transition:.15s;
}
.admin-sidebar a:hover{
  background:#f0f4fb;
  color:var(--admin);
}
.admin-sidebar a.active{
  background:#e8eefb;
  color:var(--admin);
  border-left-color:var(--admin);
  font-weight:700;
}
.admin-sidebar a i{
  width:20px;text-align:center;
  font-size:.95rem;
  color:#8a93a6;
}
.admin-sidebar a.active i{color:var(--admin);}
.admin-sidebar a .badge-pill{
  margin-left:auto;
  background:var(--admin-accent);
  color:#fff;
  font-size:.68rem;
  font-weight:800;
  padding:2px 8px;
  border-radius:10px;
}

/* ---------- MAIN ---------- */
.admin-main{
  margin-left:240px;
  padding:24px;
  min-height:calc(100vh - 112px);
}

/* ---------- SHARED CARD ---------- */
.admin-card{
  background:#fff;border-radius:10px;
  box-shadow:0 2px 8px rgba(0,0,0,.05);
  border:1px solid #eaecf2;
}
.admin-page-title{
  color:var(--admin);
  font-weight:800;
  margin:0 0 4px;
}
.admin-page-sub{
  color:#8a93a6;font-size:.85rem;margin:0 0 20px;
}
.admin-card-head{
  display:flex;justify-content:space-between;align-items:center;
  padding:14px 18px;border-bottom:1px solid #f0f0f5;
}
.admin-card-head h6{margin:0;font-weight:800;color:var(--admin);}
.admin-card-head a{color:var(--admin);text-decoration:none;font-weight:600;font-size:.85rem;}
.admin-row{
  display:flex;justify-content:space-between;align-items:center;
  padding:12px 18px;border-bottom:1px dashed #eee;
}
.admin-row:last-child{border-bottom:none;}

/* Stat tiles */
.admin-stat{
  display:block;background:#fff;border-radius:10px;padding:18px;
  text-align:center;text-decoration:none;color:#333;
  box-shadow:0 2px 8px rgba(0,0,0,.05);transition:.2s;
  border:1px solid transparent;
}
.admin-stat:hover{transform:translateY(-3px);box-shadow:0 6px 14px rgba(0,0,0,.1);
  color:var(--admin);border-color:var(--admin);}
.admin-stat.highlight{border-color:#f5a623;background:#fffbe9;}
.admin-stat i{font-size:1.5rem;color:var(--admin);display:block;margin-bottom:6px;}
.admin-stat-num{font-weight:800;font-size:1.6rem;color:var(--admin);}
.admin-stat-label{font-size:.78rem;color:#888;text-transform:uppercase;letter-spacing:.5px;}

.quick-tile{
  display:flex;flex-direction:column;align-items:center;gap:6px;
  background:#f6f8ff;color:var(--admin);text-decoration:none;
  border-radius:10px;padding:18px 10px;text-align:center;
  font-weight:600;font-size:.85rem;transition:.2s;
}
.quick-tile:hover{background:#e5ecff;color:var(--admin-dark);}
.quick-tile i{font-size:1.4rem;}

/* ---------- MOBILE ---------- */
.sidebar-toggle{
  display:none;background:transparent;border:0;color:#fff;
  font-size:1.2rem;padding:4px 8px;
}
.sidebar-backdrop{
  display:none;
  position:fixed;inset:64px 0 0 0;
  background:rgba(0,0,0,.35);
  z-index:1025;
}
@media (max-width:992px){
  .admin-sidebar{transform:translateX(-100%);}
  .admin-sidebar.open{transform:translateX(0);}
  .admin-main{margin-left:0;}
  .sidebar-toggle{display:inline-block;}
  .sidebar-backdrop.open{display:block;}
}
</style>
</head>
<body>

<!-- ================= TOP BAR ================= -->
<div class="admin-topbar">
  <div class="d-flex align-items-center h-100 px-3 px-md-4">

    <button class="sidebar-toggle" onclick="toggleAdminSidebar()">
      <i class="fa fa-bars"></i>
    </button>

    <a href="dashboard.php" class="logo">
      <i class="fa fa-user-shield"></i>
      <span class="d-none d-sm-inline">Dynamic Mart</span>
      <span class="role-badge d-none d-md-inline"><?= htmlspecialchars($adminRole) ?></span>
    </a>

    <div class="ms-auto d-flex align-items-center gap-2 gap-md-3">

      <a href="../index.php" target="_blank" class="d-none d-md-inline">
        <i class="fa fa-external-link-alt"></i> View Site
      </a>

      <div class="user-chip">
        <div class="avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
        <span class="d-none d-sm-inline"><?= htmlspecialchars($adminName) ?></span>
      </div>

      <a href="logout.php" title="Logout">
        <i class="fa fa-sign-out-alt"></i>
      </a>
    </div>
  </div>
</div>

<!-- ================= SIDEBAR ================= -->
<aside class="admin-sidebar" id="adminSidebar">

  <div class="section-label">Overview</div>
  <a href="dashboard.php" class="<?= $current==='dashboard.php'?'active':'' ?>">
    <i class="fa fa-gauge-high"></i> Dashboard
  </a>

  <div class="section-label">Sellers</div>
  <a href="sellers.php?status=pending"
     class="<?= $current==='sellers.php' && ($_GET['status']??'')==='pending' ? 'active' : '' ?>">
    <i class="fa fa-user-clock"></i> Pending Approvals
    <?php if ($pendingCount): ?>
      <span class="badge-pill"><?= $pendingCount ?></span>
    <?php endif; ?>
  </a>
  <a href="sellers.php?status=active"
     class="<?= $current==='sellers.php' && ($_GET['status']??'')==='active' ? 'active' : '' ?>">
    <i class="fa fa-store"></i> Active Sellers
  </a>
  <a href="sellers.php?status=rejected"
     class="<?= $current==='sellers.php' && ($_GET['status']??'')==='rejected' ? 'active' : '' ?>">
    <i class="fa fa-user-xmark"></i> Rejected
  </a>

  <div class="section-label">Catalog</div>
  <a href="products.php" class="<?= $current==='products.php'?'active':'' ?>">
    <i class="fa fa-boxes"></i> All Products
  </a>
  <a href="categories.php" class="<?= $current==='categories.php'?'active':'' ?>">
    <i class="fa fa-tags"></i> Categories
  </a>

  <div class="section-label">Sales</div>
  <a href="orders.php" class="<?= $current==='orders.php'?'active':'' ?>">
    <i class="fa fa-shopping-bag"></i> Orders
  </a>

  <div class="section-label">Users</div>
  <a href="users.php" class="<?= $current==='users.php'?'active':'' ?>">
    <i class="fa fa-users"></i> Buyers
  </a>
  <a href="admins.php" class="<?= $current==='admins.php'?'active':'' ?>">
    <i class="fa fa-user-shield"></i> Staff
  </a>

  <div class="section-label">System</div>
  <a href="settings.php" class="<?= $current==='settings.php'?'active':'' ?>">
    <i class="fa fa-gear"></i> Settings
  </a>
  <a href="logout.php">
    <i class="fa fa-sign-out-alt"></i> Logout
  </a>
</aside>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleAdminSidebar()"></div>

<main class="admin-main">