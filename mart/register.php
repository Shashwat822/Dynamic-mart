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
$name  = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if ($name === '' || $email === '' || $pass === '') {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        /* Email already used? */
        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = :e");
        $stmt->execute([':e' => $email]);

        if ($stmt->fetch()) {
            $error = 'This email is already registered. Try logging in.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);

            $ins = $pdo->prepare("
                INSERT INTO users (name, email, phone, password)
                VALUES (:n, :e, :p, :h)
            ");
            $ins->execute([
                ':n' => $name,
                ':e' => $email,
                ':p' => $phone,
                ':h' => $hash,
            ]);

            session_regenerate_id(true);
            $_SESSION['user_id']   = $pdo->lastInsertId();
            $_SESSION['user_name'] = $name;

            /* Redirect where the user was heading, else home */
            $back = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);

            header('Location: ' . $back);
            exit;
        }
    }
}

/* ---------- All redirects done — now output HTML ---------- */
require_once 'includes/header.php';
?>

<div class="container my-5" style="max-width:520px;">
  <div class="bg-white rounded shadow-sm p-4">

    <div class="text-center mb-4">
      <div style="width:64px;height:64px;border-radius:50%;background:#f6f0ff;
                  color:var(--brand);display:inline-flex;align-items:center;
                  justify-content:center;font-size:1.6rem;">
        <i class="fa fa-user-plus"></i>
      </div>
      <h4 class="mt-3 mb-0" style="color:var(--brand);font-weight:800;">
        Create Account
      </h4>
      <div class="text-muted small">Join Dynamic Mart in a few seconds</div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2 small">
        <i class="fa fa-exclamation-circle me-1"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="mb-3">
        <label class="form-label small text-muted">Full Name *</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="fa fa-user"></i></span>
          <input type="text" name="name" class="form-control" required
                 placeholder="e.g. Ram Bahadur"
                 value="<?= htmlspecialchars($name) ?>"
                 autocomplete="name" autofocus>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small text-muted">Email *</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="fa fa-envelope"></i></span>
          <input type="email" name="email" class="form-control" required
                 placeholder="you@example.com"
                 value="<?= htmlspecialchars($email) ?>"
                 autocomplete="email">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small text-muted">Phone</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="fa fa-phone"></i></span>
          <input type="text" name="phone" class="form-control"
                 placeholder="98XXXXXXXX"
                 value="<?= htmlspecialchars($phone) ?>"
                 autocomplete="tel">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small text-muted">Password * (min 6 characters)</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="fa fa-lock"></i></span>
          <input type="password" name="password" id="passwordField"
                 class="form-control" required
                 autocomplete="new-password">
          <button type="button" class="btn btn-outline-secondary"
                  onclick="togglePassword('passwordField','eyeIcon1')" tabindex="-1">
            <i class="fa fa-eye" id="eyeIcon1"></i>
          </button>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small text-muted">Confirm Password *</label>
        <div class="input-group">
          <span class="input-group-text bg-light"><i class="fa fa-lock"></i></span>
          <input type="password" name="password2" id="passwordField2"
                 class="form-control" required
                 autocomplete="new-password">
          <button type="button" class="btn btn-outline-secondary"
                  onclick="togglePassword('passwordField2','eyeIcon2')" tabindex="-1">
            <i class="fa fa-eye" id="eyeIcon2"></i>
          </button>
        </div>
      </div>

      <div class="form-check mb-3 small">
        <input class="form-check-input" type="checkbox" name="agree"
               id="agree" value="1" required>
        <label class="form-check-label text-muted" for="agree">
          I agree to the <a href="page.php?slug=terms" target="_blank">Terms</a>
          and <a href="page.php?slug=privacy" target="_blank">Privacy Policy</a>
        </label>
      </div>

      <button class="btn w-100 text-white" style="background:var(--brand);font-weight:600;">
        <i class="fa fa-user-plus me-1"></i> Create Account
      </button>

      <div class="text-center my-3 text-muted small">— OR —</div>

      <a href="login.php" class="btn btn-outline-secondary w-100">
        <i class="fa fa-sign-in-alt me-1"></i> Already have an account? Login
      </a>
    </form>
  </div>

  <div class="text-center mt-3 small text-muted">
    <i class="fa fa-shield-alt me-1" style="color:var(--brand);"></i>
    Passwords are stored hashed and encrypted.
  </div>
</div>

<script>
function togglePassword(inputId, iconId) {
  const f = document.getElementById(inputId);
  const i = document.getElementById(iconId);
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