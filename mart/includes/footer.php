<?php
$base = $base ?? '/mart/';
?>

<!-- ================= FOOTER ================= -->
<footer class="mart-footer mt-5">
  <div class="container py-5">
    <div class="row g-4">

      <div class="col-6 col-md-3">
        <h6><?= htmlspecialchars(t('customer_care')) ?></h6>
        <ul>
          <li><a href="<?= $base ?>page.php?slug=help"><i class="fa fa-headset"></i> <?= htmlspecialchars(t('help_center')) ?></a></li>
          <li><a href="<?= $base ?>page.php?slug=how-to-buy"><i class="fa fa-cart-shopping"></i> <?= htmlspecialchars(t('how_buy')) ?></a></li>
          <li><a href="<?= $base ?>page.php?slug=returns"><i class="fa fa-rotate-left"></i> <?= htmlspecialchars(t('returns')) ?></a></li>
          <li><a href="<?= $base ?>page.php?slug=shipping"><i class="fa fa-truck"></i> <?= htmlspecialchars(t('shipping')) ?></a></li>
          <li><a href="<?= $base ?>page.php?slug=contact"><i class="fa fa-phone"></i> <?= htmlspecialchars(t('contact')) ?></a></li>
        </ul>
      </div>

      <div class="col-6 col-md-3">
        <h6><?= htmlspecialchars(t('about')) ?></h6>
        <ul>
          <li><a href="<?= $base ?>page.php?slug=about"><i class="fa fa-circle-info"></i> <?= htmlspecialchars(t('about_us')) ?></a></li>
          <li><a href="<?= $base ?>page.php?slug=seller"><i class="fa fa-user-plus"></i> <?= htmlspecialchars(t('seller')) ?></a></li>
          <li><a href="<?= $base ?>page.php?slug=privacy"><i class="fa fa-lock"></i> <?= htmlspecialchars(t('privacy')) ?></a></li>
          <li><a href="<?= $base ?>page.php?slug=terms"><i class="fa fa-file-contract"></i> <?= htmlspecialchars(t('terms')) ?></a></li>
        </ul>
      </div>

      <div class="col-6 col-md-3">
        <h6><?= htmlspecialchars(t('payment_methods')) ?></h6>
        <ul>
          <li><span class="pay-badge">VISA</span> <?= htmlspecialchars(t('visa')) ?></li>
          <li><span class="pay-badge"><i class="fa fa-cc-mastercard"></i></span> <?= htmlspecialchars(t('master')) ?></li>
          <li><span class="pay-badge"><i class="fa fa-money-bill-wave"></i></span> <?= htmlspecialchars(t('cod')) ?></li>
          <li><span class="pay-badge"><i class="fa fa-globe"></i></span> <?= htmlspecialchars(t('online')) ?></li>
        </ul>
      </div>

      <div class="col-6 col-md-3">
        <h6><?= htmlspecialchars(t('follow_us')) ?></h6>
        <div class="social mb-4">
          <a href="#"><i class="fab fa-facebook-f"></i></a>
          <a href="#"><i class="fab fa-instagram"></i></a>
          <a href="#"><i class="fab fa-youtube"></i></a>
        </div>

        <h6><?= htmlspecialchars(t('download_app')) ?></h6>
        <a href="#" class="store-badge">
          <i class="fab fa-google-play"></i>
          <div><small>GET IT ON</small><strong>Google Play</strong></div>
        </a>
        <a href="#" class="store-badge">
          <i class="fab fa-apple"></i>
          <div><small>Download on the</small><strong>App Store</strong></div>
        </a>
      </div>

    </div>
  </div>
</footer>
<!-- =============== /FOOTER =================== -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.alert').forEach(function(a){
  setTimeout(function(){
    a.style.transition='opacity .4s';
    a.style.opacity='0';
    setTimeout(function(){a.remove();},400);
  },3000);
});
</script>
</body>
</html>