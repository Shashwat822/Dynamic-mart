<?php
require_once 'includes/header.php';

$slug = $_GET['slug'] ?? '';

$pages = [
    'help'       => ['Help Center',           'We are here to help 24/7. Email support@dynamicmart.com or call +977-1-XXXXXXX.'],
    'how-to-buy' => ['How to Buy',            'Browse products → Add to cart → Checkout → Pay on delivery or online.'],
    'returns'    => ['Returns & Refunds',     'Products can be returned within 7 days of delivery in original condition.'],
    'shipping'   => ['Shipping Information',  'Free delivery inside Kathmandu Valley. 2–4 days elsewhere in Nepal.'],
    'contact'    => ['Contact Us',            'Email: info@dynamicmart.com | Phone: +977-1-XXXXXXX | Address: Kathmandu, Nepal.'],
    'about'      => ['About Us',              'Dynamic Mart is an online marketplace offering electronics, fashion, home goods and more.'],
    'seller'     => ['Become a Seller',       'Register as a seller and start listing your products today. Email sellers@dynamicmart.com.'],
    'privacy'    => ['Privacy Policy',        'We respect your privacy. Your data is never shared with third parties.'],
    'terms'      => ['Terms & Conditions',    'By using Dynamic Mart you agree to our standard terms of service.'],
];

if (!isset($pages[$slug])) {
    echo '<div class="container my-5"><div class="alert alert-warning">Page not found.</div></div>';
    require_once 'includes/footer.php';
    exit;
}

[$title, $body] = $pages[$slug];
?>

<div class="container my-5" style="max-width:800px;">
  <div class="bg-white rounded shadow-sm p-4">
    <h3 style="color:var(--brand);font-weight:800;"><?= htmlspecialchars($title) ?></h3>
    <hr>
    <p class="text-muted"><?= nl2br(htmlspecialchars($body)) ?></p>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>