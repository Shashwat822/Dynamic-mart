<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../db.php';

if (!empty($_SESSION['seller_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seller_type  = $_POST['seller_type']  ?? 'business';

    /* Shop / Pickup */
    $shop_name    = trim($_POST['shop_name']    ?? '');
    $shop_address = trim($_POST['shop_address'] ?? '');
    $shop_phone   = trim($_POST['shop_phone']   ?? '');
    $shop_email   = trim($_POST['shop_email']   ?? '');

    /* Owner */
    $owner_name    = trim($_POST['owner_name']    ?? '');
    $owner_phone   = trim($_POST['owner_phone']   ?? '');
    $owner_address = trim($_POST['owner_address'] ?? '');
    $owner_email   = strtolower(trim($_POST['owner_email'] ?? ''));

    /* Auth */
    $pass  = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    /* Business-only */
    $pan  = trim($_POST['pan_number']  ?? '');
    $vat  = trim($_POST['vat_number']  ?? '');

    /* Individual-only */
    $cit  = trim($_POST['citizenship_no'] ?? '');

    /* ---------- Validation ---------- */
    if (!in_array($seller_type, ['business','individual'], true)) {
        $error = 'Invalid seller type.';
    } elseif ($seller_type === 'business' && $shop_name === '') {
        $error = 'Shop name is required for business sellers.';
    } elseif ($shop_address === '') {
        $error = $seller_type === 'business'
               ? 'Shop address is required.'
               : 'Pickup address is required.';
    } elseif ($owner_name === '' || $owner_address === '') {
        $error = 'Your name and permanent address are required.';
    } elseif (!filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } elseif ($seller_type === 'business' && $pan === '') {
        $error = 'PAN number is required for business sellers.';
    } elseif ($seller_type === 'individual' && $cit === '') {
        $error = 'Citizenship number is required for individual sellers.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM sellers WHERE LOWER(owner_email) = :e");
        $chk->execute([':e' => $owner_email]);
        if ($chk->fetch()) {
            $error = 'This email is already registered as a seller.';
        }
    }

    /* Auto-fill shop name for individuals */
    if ($seller_type === 'individual' && $shop_name === '') {
        $shop_name = $owner_name;
    }

    /* ---------- File uploads ---------- */
    $uploadDir = __DIR__ . '/../assets/uploads/sellers/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    function saveUpload($field, $uploadDir) {
        if (empty($_FILES[$field]['name'])) return null;
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        $ok  = ['jpg','jpeg','png','webp','pdf'];
        if (!in_array($ext, $ok)) return '__bad__';
        $name = $field . '_' . time() . '_' . rand(100,999) . '.' . $ext;
        if (move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . $name)) {
            return 'assets/uploads/sellers/' . $name;
        }
        return null;
    }

    $panFile = $citFrontFile = $citBackFile = null;

    if (!$error) {
        if ($seller_type === 'business') {
            $panFile = saveUpload('pan_cert_file', $uploadDir);
            if ($panFile === '__bad__') $error = 'PAN certificate must be JPG, PNG, WEBP or PDF.';
        } else {
            $citFrontFile = saveUpload('cit_front_file', $uploadDir);
            $citBackFile  = saveUpload('cit_back_file',  $uploadDir);
            if ($citFrontFile === '__bad__' || $citBackFile === '__bad__') {
                $error = 'Citizenship images must be JPG, PNG, WEBP or PDF.';
            }
        }
    }

    /* ---------- Insert ---------- */
    if (!$error) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $ins = $pdo->prepare("
            INSERT INTO sellers
              (seller_type, shop_name, shop_address, shop_phone, shop_email,
               owner_name, owner_phone, owner_address, owner_email,
               pan_number, vat_number, citizenship_no,
               pan_cert_file, cit_front_file, cit_back_file,
               password, status)
            VALUES
              (:stype, :sn, :sa, :sp, :se,
               :on, :op, :oa, :oe,
               :pan, :vat, :cit,
               :panf, :citf, :citb,
               :pw, 'pending')
        ");
        $ins->execute([
            ':stype' => $seller_type,
            ':sn'    => $shop_name,
            ':sa'    => $shop_address,
            ':sp'    => $shop_phone,
            ':se'    => $shop_email,
            ':on'    => $owner_name,
            ':op'    => $owner_phone,
            ':oa'    => $owner_address,
            ':oe'    => $owner_email,
            ':pan'   => $pan ?: null,
            ':vat'   => $vat ?: null,
            ':cit'   => $cit ?: null,
            ':panf'  => $panFile,
            ':citf'  => $citFrontFile,
            ':citb'  => $citBackFile,
            ':pw'    => $hash,
        ]);

        $_SESSION['seller_pending_email'] = $owner_email;
        header('Location: pending.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5" style="max-width:760px;">
  <div class="bg-white rounded shadow-sm p-4">

    <div class="text-center mb-4">
      <div style="width:64px;height:64px;border-radius:50%;background:#fff0e6;
                  color:var(--accent);display:inline-flex;align-items:center;
                  justify-content:center;font-size:1.6rem;">
        <i class="fa fa-store"></i>
      </div>
      <h4 class="mt-3 mb-0" style="color:var(--brand);font-weight:800;">Become a Seller</h4>
      <div class="text-muted small">Complete your profile to start selling</div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2 small">
        <i class="fa fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>

      <!-- ========== SELLER TYPE ========== -->
      <h6 class="section-title"><i class="fa fa-tag me-2"></i> Seller Type</h6>
      <div class="row g-2 mb-4">
        <div class="col-md-6">
          <label class="type-card <?= ($old['seller_type'] ?? 'business') === 'business' ? 'active' : '' ?>">
            <input type="radio" name="seller_type" value="business" class="d-none"
                   <?= ($old['seller_type'] ?? 'business') === 'business' ? 'checked' : '' ?>
                   onchange="switchType()">
            <i class="fa fa-building"></i>
            <div>
              <strong>Business Seller</strong>
              <div class="small text-muted">Sell new products, brand items</div>
            </div>
          </label>
        </div>
        <div class="col-md-6">
          <label class="type-card <?= ($old['seller_type'] ?? '') === 'individual' ? 'active' : '' ?>">
            <input type="radio" name="seller_type" value="individual" class="d-none"
                   <?= ($old['seller_type'] ?? '') === 'individual' ? 'checked' : '' ?>
                   onchange="switchType()">
            <i class="fa fa-user"></i>
            <div>
              <strong>Individual / Second-hand</strong>
              <div class="small text-muted">Sell used or personal items</div>
            </div>
          </label>
        </div>
      </div>

      <!-- ========== SHOP / PICKUP INFO ========== -->
      <h6 class="section-title">
        <i class="fa fa-store me-2"></i>
        <span id="shopSectionLabel">Shop Information</span>
      </h6>
      <div class="row g-3 mb-4">

        <div class="col-md-6" id="shopNameWrap">
          <label class="form-label small text-muted">Shop Name *</label>
          <input type="text" name="shop_name" id="shopNameInput" class="form-control"
                 value="<?= htmlspecialchars($old['shop_name'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label small text-muted">
            <span id="shopPhoneLabel">Shop Contact Number</span>
          </label>
          <input type="text" name="shop_phone" class="form-control"
                 placeholder="98XXXXXXXX"
                 value="<?= htmlspecialchars($old['shop_phone'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label small text-muted">
            <span id="shopEmailLabel">Shop Email</span>
          </label>
          <input type="email" name="shop_email" class="form-control"
                 value="<?= htmlspecialchars($old['shop_email'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label small text-muted">
            <span id="shopAddressLabel">Shop Address *</span>
          </label>
          <input type="text" name="shop_address" class="form-control" required
                 value="<?= htmlspecialchars($old['shop_address'] ?? '') ?>">
        </div>

        <div class="col-12" id="individualHint" style="display:none;">
          <div class="alert alert-light border small mb-0 py-2">
            <i class="fa fa-info-circle me-1" style="color:var(--brand);"></i>
            As an individual seller, you don't need a shop name —
            your products will appear under your own name.
          </div>
        </div>
      </div>

      <!-- ========== YOUR DETAILS ========== -->
      <h6 class="section-title"><i class="fa fa-user me-2"></i> Your Details</h6>
      <div class="row g-3 mb-4">

        <div class="col-md-6">
          <label class="form-label small text-muted">Full Name *</label>
          <input type="text" name="owner_name" class="form-control" required
                 value="<?= htmlspecialchars($old['owner_name'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label small text-muted">Alternate Number <span class="text-muted">(optional)</span></label>
          <input type="text" name="owner_phone" class="form-control"
                 placeholder="98XXXXXXXX"
                 value="<?= htmlspecialchars($old['owner_phone'] ?? '') ?>">
        </div>

        <div class="col-12">
          <label class="form-label small text-muted">Permanent Address *</label>
          <input type="text" name="owner_address" class="form-control" required
                 value="<?= htmlspecialchars($old['owner_address'] ?? '') ?>">
        </div>

        <div class="col-12">
          <label class="form-label small text-muted">Login Email * <span class="text-muted">(used for signing in)</span></label>
          <input type="email" name="owner_email" class="form-control" required
                 value="<?= htmlspecialchars($old['owner_email'] ?? '') ?>">
        </div>
      </div>

      <!-- ========== BUSINESS FIELDS ========== -->
      <div id="businessFields">
        <h6 class="section-title"><i class="fa fa-file-invoice me-2"></i> Business Documents</h6>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label small text-muted">PAN Number *</label>
            <input type="text" name="pan_number" class="form-control"
                   value="<?= htmlspecialchars($old['pan_number'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">VAT Number <span class="text-muted">(optional)</span></label>
            <input type="text" name="vat_number" class="form-control"
                   value="<?= htmlspecialchars($old['vat_number'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small text-muted">Upload PAN Certificate *</label>
            <input type="file" name="pan_cert_file" class="form-control" accept="image/*,.pdf">
            <div class="small text-muted mt-1">JPG, PNG, WEBP or PDF · Max 3 MB</div>
          </div>
        </div>
      </div>

      <!-- ========== INDIVIDUAL FIELDS ========== -->
      <div id="individualFields" style="display:none;">
        <h6 class="section-title"><i class="fa fa-id-card me-2"></i> Citizenship Documents</h6>
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label small text-muted">Citizenship Number *</label>
            <input type="text" name="citizenship_no" class="form-control"
                   value="<?= htmlspecialchars($old['citizenship_no'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Upload Citizenship (Front) *</label>
            <input type="file" name="cit_front_file" class="form-control" accept="image/*,.pdf">
          </div>
          <div class="col-md-6">
            <label class="form-label small text-muted">Upload Citizenship (Back) *</label>
            <input type="file" name="cit_back_file" class="form-control" accept="image/*,.pdf">
          </div>
        </div>
      </div>

      <!-- ========== PASSWORD ========== -->
      <h6 class="section-title"><i class="fa fa-lock me-2"></i> Login Password</h6>
      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label small text-muted">Password * (min 6)</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label small text-muted">Confirm Password *</label>
          <input type="password" name="password2" class="form-control" required>
        </div>
      </div>

      <div class="form-check small mb-3">
        <input class="form-check-input" type="checkbox" name="agree" id="agree" required>
        <label for="agree" class="form-check-label text-muted">
          I confirm the details are correct and agree to the
          <a href="../page.php?slug=terms" target="_blank">Terms</a>.
        </label>
      </div>

      <button class="btn w-100 text-white fw-bold" style="background:var(--accent);">
        <i class="fa fa-paper-plane me-1"></i> Submit for Approval
      </button>

      <p class="text-center small mt-3 mb-0">
        Already a seller? <a href="login.php">Login here</a>
      </p>
    </form>
  </div>
</div>

<style>
.section-title{
  color:var(--brand);font-weight:800;font-size:.95rem;
  padding-bottom:6px;border-bottom:2px solid #f0eaf8;
  margin-bottom:12px;
}
.type-card{
  display:flex;align-items:center;gap:12px;
  border:2px solid #e2e2e8;border-radius:10px;
  padding:14px;cursor:pointer;transition:.2s;
  background:#fff;
}
.type-card:hover{border-color:var(--brand);}
.type-card.active{border-color:var(--brand);background:#f6f0ff;}
.type-card i{
  font-size:1.4rem;color:var(--brand);
  width:40px;height:40px;border-radius:50%;
  background:#f6f0ff;
  display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.type-card.active i{background:var(--brand);color:#fff;}
</style>

<script>
function switchType() {
  const type = document.querySelector('input[name="seller_type"]:checked').value;

  document.querySelectorAll('.type-card').forEach(c => c.classList.remove('active'));
  document.querySelector(`input[value="${type}"]`).closest('.type-card').classList.add('active');

  document.getElementById('businessFields').style.display   = type === 'business'   ? 'block' : 'none';
  document.getElementById('individualFields').style.display = type === 'individual' ? 'block' : 'none';

  const shopNameWrap  = document.getElementById('shopNameWrap');
  const shopNameInput = document.getElementById('shopNameInput');

  if (type === 'business') {
    shopNameWrap.style.display = '';
    shopNameInput.required = true;

    document.getElementById('shopSectionLabel').textContent  = 'Shop Information';
    document.getElementById('shopPhoneLabel').textContent    = 'Shop Contact Number';
    document.getElementById('shopEmailLabel').textContent    = 'Shop Email';
    document.getElementById('shopAddressLabel').textContent  = 'Shop Address *';
    document.getElementById('individualHint').style.display  = 'none';
  } else {
    shopNameWrap.style.display = 'none';
    shopNameInput.required = false;

    document.getElementById('shopSectionLabel').textContent  = 'Pickup / Contact Information';
    document.getElementById('shopPhoneLabel').textContent    = 'Contact Number';
    document.getElementById('shopEmailLabel').textContent    = 'Contact Email';
    document.getElementById('shopAddressLabel').textContent  = ' Address *';
    document.getElementById('individualHint').style.display  = 'block';
  }
}
switchType();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>