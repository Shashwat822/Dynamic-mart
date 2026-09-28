<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

/* Already logged in? Redirect to correct dashboard */
if (!empty($_SESSION['admin_id'])) {
    header('Location: ../admin/dashboard.php');
    exit;
}
if (!empty($_SESSION['seller_id'])) {
    header('Location: dashboard.php');
    exit;
}

/* Rate limiting */
if (!isset($_SESSION['seller_login_attempts'])) $_SESSION['seller_login_attempts'] = 0;
if (!isset($_SESSION['seller_login_lock_until'])) $_SESSION['seller_login_lock_until'] = 0;

$locked    = $_SESSION['seller_login_lock_until'] > time();
$remaining = max(0, $_SESSION['seller_login_lock_until'] - time());

$error       = '';
$email       = '';
$msg         = $_GET['msg'] ?? '';
$pendingMsg  = false;
$rejectedMsg = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $pass     = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($email === '' || $pass === '') {
        $error = 'Please enter both email and password.';
    } else {

        /* -------- 1) Admin check (silent) -------- */
        $aStmt = $pdo->prepare("SELECT id, name, password, role FROM admins WHERE LOWER(email) = :e");
        $aStmt->execute([':e' => $email]);
        $a = $aStmt->fetch();

        if ($a && password_verify($pass, $a['password'])) {
            $_SESSION['seller_login_attempts'] = 0;
            session_regenerate_id(true);

            unset($_SESSION['seller_id'], $_SESSION['seller_name'], $_SESSION['seller_email']);

            $_SESSION['admin_id']    = $a['id'];
            $_SESSION['admin_name']  = $a['name'];
            $_SESSION['admin_role']  = $a['role'];
            $_SESSION['admin_email'] = $email;

            if ($remember) {
                $params = session_get_cookie_params();
                setcookie(session_name(), session_id(), [
                    'expires'  => time() + 60 * 60 * 24 * 30,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            $back = $_SESSION['redirect_after_admin_login'] ?? '../admin/dashboard.php';
            unset($_SESSION['redirect_after_admin_login']);

            header('Location: ' . $back);
            exit;
        }

        /* -------- 2) Seller check -------- */
        $sStmt = $pdo->prepare("
            SELECT id, shop_name, password, status, admin_note
            FROM sellers
            WHERE LOWER(owner_email) = :e
        ");
        $sStmt->execute([':e' => $email]);
        $s = $sStmt->fetch();

        if ($s && password_verify($pass, $s['password'])) {
            $_SESSION['seller_login_attempts'] = 0;

            if ($s['status'] === 'pending') {
                $pendingMsg = true;
                $error = 'Your seller account is still under review.';
            } elseif ($s['status'] === 'rejected') {
                $rejectedMsg = true;
                $error = 'Your seller application was rejected.';
            } elseif ($s['status'] !== 'active') {
                $error = 'Your seller account is not active. Please contact support.';
            } else {
                session_regenerate_id(true);

                unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['admin_email']);

                $_SESSION['seller_id']    = $s['id'];
                $_SESSION['seller_name']  = $s['shop_name'];
                $_SESSION['seller_email'] = $email;

                if ($remember) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), session_id(), [
                        'expires'  => time() + 60 * 60 * 24 * 30,
                        'path'     => $params['path'],
                        'domain'   => $params['domain'],
                        'secure'   => $params['secure'],
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                }

                $back = $_SESSION['redirect_after_seller_login'] ?? 'dashboard.php';
                unset($_SESSION['redirect_after_seller_login']);

                header('Location: ' . $back);
                exit;
            }
        } else {
            $_SESSION['seller_login_attempts']++;
            if ($_SESSION['seller_login_attempts'] >= 5) {
                $_SESSION['seller_login_lock_until'] = time() + 300;
                $_SESSION['seller_login_attempts']   = 0;
                $locked    = true;
                $remaining = 300;
            }
            $error = 'Invalid email or password.';
        }
    }
}

$remainTxt = '';
if ($locked && $remaining > 0) {
    $remainTxt = floor($remaining / 60) . ' min ' . ($remaining % 60) . ' sec';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5" style="max-width:960px;">
  <div class="row g-0 auth-wrap">

    <!-- LEFT: Marketing panel -->
    <div class="col-lg-5 d-none d-lg-flex auth-side">
      <div>
        <div class="auth-side-logo">
          <i class="fa fa-store"></i>
        </div>
        <h3 class="auth-side-title">Grow your business on Dynamic Mart</h3>
        <p class="auth-side-sub">
          Reach thousands of buyers across Nepal. List products, manage orders,
          and track your shop — all in one place.
        </p>
        <ul class="auth-side-list">
          <li><i class="fa fa-check-circle"></i> Free to join</li>
          <li><i class="fa fa-check-circle"></i> Zero listing fees</li>
          <li><i class="fa fa-check-circle"></i> Fast payouts</li>
          <li><i class="fa fa-check-circle"></i> Dedicated seller support</li>
        </ul>
      </div>
    </div>

    <!-- RIGHT: Login card -->
    <div class="col-lg-7 auth-form-col">
      <div class="auth-card">

        <div class="text-center mb-4 d-lg-none">
          <div class="auth-logo-sm">
            <i class="fa fa-store"></i>
          </div>
        </div>

        <h4 class="auth-title">Seller Login</h4>
        <p class="auth-sub">Sign in to manage your shop</p>

        <?php if ($msg === 'login_required'): ?>
          <div class="alert alert-warning py-2 small">
            <i class="fa fa-lock me-1"></i> Please login to your seller account to continue.
          </div>
        <?php endif; ?>

        <?php if ($locked): ?>
          <div class="alert alert-danger py-3 small">
            <div class="d-flex align-items-start gap-2">
              <i class="fa fa-shield-alt mt-1" style="font-size:1.1rem;"></i>
              <div>
                <strong>Too many failed attempts</strong><br>
                Please wait <strong><?= htmlspecialchars($remainTxt) ?></strong> before trying again.
              </div>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($pendingMsg): ?>
          <div class="status-card pending">
            <div class="status-icon"><i class="fa fa-hourglass-half"></i></div>
            <div class="status-content">
              <h6>Account pending approval</h6>
              <p>
                Your seller account is under review by our team.
                You will be able to log in as soon as it is activated
                — usually within <strong>24–48 hours</strong>.
              </p>
              <div class="d-flex gap-2 flex-wrap">
                <a href="pending.php" class="btn btn-sm btn-warning text-dark">
                  <i class="fa fa-info-circle me-1"></i> What happens next?
                </a>
                <a href="../page.php?slug=contact" class="btn btn-sm btn-outline-secondary">
                  <i class="fa fa-headset me-1"></i> Contact support
                </a>
              </div>
            </div>
          </div>
        <?php elseif ($rejectedMsg): ?>
          <div class="status-card rejected">
            <div class="status-icon"><i class="fa fa-times-circle"></i></div>
            <div class="status-content">
              <h6>Application was rejected</h6>
              <p>
                Unfortunately your seller application was not approved.
                Our team may have sent details to your email.
              </p>
              <div class="d-flex gap-2 flex-wrap">
                <a href="../page.php?slug=contact" class="btn btn-sm btn-danger">
                  <i class="fa fa-headset me-1"></i> Contact support
                </a>
                <a href="register.php" class="btn btn-sm btn-outline-secondary">
                  <i class="fa fa-refresh me-1"></i> Apply again
                </a>
              </div>
            </div>
          </div>
        <?php elseif ($error && !$locked): ?>
          <div class="alert alert-danger py-2 small">
            <i class="fa fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form method="post" novalidate autocomplete="on" <?= $locked ? 'style="opacity:.6;pointer-events:none;"' : '' ?>>
          <div class="mb-3">
            <label class="form-label small text-muted" for="loginEmail">Email</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="fa fa-envelope"></i></span>
              <input type="email"
                     name="email"
                     id="loginEmail"
                     class="form-control"
                     placeholder="you@example.com"
                     autocomplete="username"
                     value="<?= htmlspecialchars($email) ?>"
                     required
                     <?= !$locked ? 'autofocus' : '' ?>>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label small text-muted" for="loginPass">Password</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="fa fa-lock"></i></span>
              <input type="password"
                     name="password"
                     id="loginPass"
                     class="form-control"
                     placeholder="••••••••"
                     autocomplete="current-password"
                     required>
              <button type="button"
                      class="btn btn-outline-secondary"
                      onclick="togglePw('loginPass','eyeIcon')"
                      tabindex="-1"
                      aria-label="Show password">
                <i class="fa fa-eye" id="eyeIcon"></i>
              </button>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3 small">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="remember" id="remember">
              <label class="form-check-label text-muted" for="remember">Remember me</label>
            </div>
            <a href="../page.php?slug=contact" class="text-decoration-none">Forgot password?</a>
          </div>

          <button class="btn w-100 text-white fw-bold auth-btn">
            <i class="fa fa-sign-in-alt me-1"></i> Login to Seller Account
          </button>

          <div class="divider"><span>New here?</span></div>

          <a href="register.php" class="btn btn-outline-secondary w-100">
            <i class="fa fa-store me-1"></i> Create Seller Account
          </a>

          <p class="text-center small text-muted mt-3 mb-0">
            Buyer account? <a href="../login.php">Login here</a>
          </p>
        </form>
      </div>
    </div>
  </div>

  <div class="text-center mt-3 small text-muted">
    <i class="fa fa-shield-alt me-1" style="color:var(--brand);"></i>
    Seller accounts require admin approval before use.
  </div>
</div>

<style>
.auth-wrap{
  background:#fff;
  border-radius:14px;
  overflow:hidden;
  box-shadow:0 6px 24px rgba(0,0,0,.06);
}
.auth-side{
  background:linear-gradient(140deg,var(--brand) 0%,var(--brand-dark) 100%);
  color:#fff;
  padding:40px 32px;
  align-items:center;
}
.auth-side-logo{
  width:64px;height:64px;border-radius:50%;
  background:rgba(255,255,255,.15);
  display:flex;align-items:center;justify-content:center;
  font-size:1.6rem;margin-bottom:20px;
}
.auth-side-title{font-weight:800;font-size:1.5rem;line-height:1.25;margin-bottom:10px;}
.auth-side-sub{font-size:.92rem;opacity:.9;line-height:1.5;}
.auth-side-list{list-style:none;padding:0;margin:24px 0 0;}
.auth-side-list li{padding:6px 0;font-size:.9rem;}
.auth-side-list i{color:#b6e6b6;margin-right:8px;}

.auth-form-col{padding:0;display:flex;align-items:center;}
.auth-card{padding:40px 40px;width:100%;}
.auth-logo-sm{
  width:56px;height:56px;border-radius:50%;
  background:#fff0e6;color:var(--accent);
  display:inline-flex;align-items:center;justify-content:center;
  font-size:1.4rem;
}
.auth-title{color:var(--brand);font-weight:800;margin:0 0 4px;}
.auth-sub{color:#888;font-size:.9rem;margin:0 0 22px;}

.auth-btn{background:var(--accent);border:none;}
.auth-btn:hover{background:#e65f00;color:#fff;}

.status-card{
  display:flex;gap:14px;border-radius:10px;
  padding:14px 16px;margin-bottom:18px;border:1px solid transparent;
}
.status-card.pending{background:#fff7e0;border-color:#f5dfa1;color:#8a6d1a;}
.status-card.pending .status-icon{background:#ffe6a1;color:#8a6d1a;}
.status-card.rejected{background:#ffe5e5;border-color:#f7c2c2;color:#a02020;}
.status-card.rejected .status-icon{background:#ffc9c9;color:#a02020;}
.status-icon{
  width:40px;height:40px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;flex-shrink:0;
}
.status-content h6{margin:0 0 4px;font-weight:800;}
.status-content p{font-size:.85rem;margin:0 0 10px;line-height:1.45;}

.divider{position:relative;text-align:center;margin:20px 0;}
.divider::before{
  content:"";position:absolute;top:50%;left:0;right:0;
  height:1px;background:#eee;
}
.divider span{
  background:#fff;position:relative;padding:0 12px;
  font-size:.8rem;color:#aaa;text-transform:uppercase;letter-spacing:.5px;
}

@media (max-width:992px){
  .auth-card{padding:30px 24px;}
  .auth-side{padding:30px 24px;}
}
</style>

<script>
function togglePw(inputId, iconId) {
  const f = document.getElementById(inputId);
  const i = document.getElementById(iconId);
  if (f.type === 'password') {
    f.type = 'text';
    i.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    f.type = 'password';
    i.classList.replace('fa-eye-slash', 'fa-eye');
  }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>