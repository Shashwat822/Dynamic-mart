<?php
/* ------------------------------------------------------------------
   Handle POST BEFORE any HTML output.
   Do NOT require header.php at the top — that outputs HTML.
   ------------------------------------------------------------------ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

/* Already logged in? Send them home. */
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';
$msg   = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $pass     = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($email === '' || $pass === '') {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, password FROM users WHERE LOWER(email) = :e");
        $stmt->execute([':e' => $email]);
        $u = $stmt->fetch();

        if ($u && password_verify($pass, $u['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id']   = $u['id'];
            $_SESSION['user_name'] = $u['name'];

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

            $back = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);

            header('Location: ' . $back);
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

/* ---------- All redirects done — now output HTML ---------- */
require_once 'includes/header.php';
?>

<div class="container my-5" style="max-width:460px;">
  <div class="bg-white rounded shadow-sm p-4">

    <div class="text-center mb-4">
      <div style="width:64px;height:64px;border-radius:50%;background:#f6f0ff;
                  color:var(--brand);display:inline-flex;align-items:center;
                  justify-content:center;font-size:1.6rem;">
        <i class="fa fa-user-lock"></i>
      </div>
      <h4 class="mt-3 mb-0" style="color:var(--brand);font-weight:800;">
        Welcome Back
      </h4>
      <div class="text-muted small">Login to your Dynamic Mart account</div>
    </div>

    <?php if ($msg === 'login_required'): ?>
      <div class="alert alert-warning py-2 small">
        <i class="fa fa-lock me-1"></i> Please login to continue.
      </div>
    <?php endif; ?>

    <?php if ($msg === 'registered'): ?>
      <div class="alert alert-success py-2 small">
        <i class="fa fa-check-circle me-1"></i> Account created. Please login.
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2 small">
        <i class="fa fa-exclamation-circle me-1"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="mb-3">
        <label class="form-label small text-muted">Email</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="fa fa-envelope"></i></span>
          <input type="email"
                 name="email"
                 class="form-control"
                 placeholder="you@example.com"
                 value="<?= htmlspecialchars($email) ?>"
                 autocomplete="email"
                 required
                 autofocus>
        </div>
      </div>

      <div class="mb-2">
        <label class="form-label small text-muted">Password</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="fa fa-lock"></i></span>
          <input type="password"
                 name="password"
                 id="passwordField"
                 class="form-control"
                 placeholder="••••••••"
                 autocomplete="current-password"
                 required>
          <button type="button"
                  class="btn btn-outline-secondary"
                  onclick="togglePassword()"
                  tabindex="-1">
            <i class="fa fa-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-3 small">
        <div class="form-check">
          <input type="checkbox" name="remember" id="remember" class="form-check-input">
          <label for="remember" class="form-check-label text-muted">Remember me</label>
        </div>
        <a href="page.php?slug=contact" class="text-decoration-none">
          Forgot password?
        </a>
      </div>

      <button class="btn w-100 text-white" style="background:var(--brand);font-weight:600;">
        <i class="fa fa-sign-in-alt me-1"></i> Login
      </button>

      <div class="text-center my-3 text-muted small">— OR —</div>

      <a href="register.php" class="btn btn-outline-secondary w-100">
        <i class="fa fa-user-plus me-1"></i> Create New Account
      </a>
    </form>
  </div>

  <!-- Trust strip -->
  <div class="text-center mt-3 small text-muted">
    <i class="fa fa-shield-alt me-1" style="color:var(--brand);"></i>
    Your data is protected with encrypted passwords.
  </div>
</div>

<script>
function togglePassword() {
  const f = document.getElementById('passwordField');
  const i = document.getElementById('eyeIcon');
  if (f.type === 'password') {
    f.type = 'text';
    i.classList.remove('fa-eye');
    i.classList.add('fa-eye-slash');
  } else {
    f.type = 'password';
    i.classList.remove('fa-eye-slash');
    i.classList.add('fa-eye');
  }
}
</script>

<?php require_once 'includes/footer.php'; ?>