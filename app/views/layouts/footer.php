  </div><!-- /.aa-content -->

  <div class="text-center text-muted-sm pb-4 no-print">
    <strong>Smart Stock</strong> v<?= APP_VERSION ?> &middot; A&amp;A Surplus, Panabo City &middot;
    IT 307 &ndash; Web Systems and Technologies 2 &middot;
    Duque &middot; Dizon &middot; Puti-an
  </div>
</main>

<script src="<?= base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/vendor/chartjs/chart.min.js') ?>"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
<?php if (!empty($inlineScript)) { echo "<script>\n" . $inlineScript . "\n</script>"; } ?>
<script>
/* Offline fallback for the home-screen app. Browsers refuse service workers on
   a plain http:// address, so this only runs over https or on localhost; on the
   shop Wi-Fi it does nothing and the site behaves as an ordinary website. */
if ('serviceWorker' in navigator && window.isSecureContext) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register(<?= js_json(base_url('sw.js')) ?>,
      { scope: <?= js_json(base_url('')) ?> }).catch(function () { /* not fatal */ });
  });
}
</script>
</body>
</html>
