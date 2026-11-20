<?php
/**
 * Open Smart Stock on a phone.
 *
 * Everything on this page is read from the machine at the moment the page is
 * opened. Nothing is written down, so nothing can go stale.
 */
?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-body">
        <h2 class="h5 mb-1">Open Smart Stock on a phone</h2>
        <p class="text-muted-sm mb-3">
          The phone must be on the same Wi-Fi as this laptop. Smart Stock is not on the
          internet &mdash; outside the shop network these addresses will not open.
        </p>

        <?php if (!$addresses): ?>
          <div class="alert alert-warning mb-0">
            <strong>This laptop has no Wi-Fi or network address right now.</strong>
            It is not connected to a network, so no phone can reach it. Connect the
            laptop to the shop Wi-Fi and reload this page.
          </div>
        <?php else: ?>
          <?php foreach ($addresses as $i => $a): ?>
            <div class="border rounded p-3 mb-3<?= $i === 0 ? ' border-primary border-2' : '' ?>">
              <div class="d-flex align-items-start gap-2 mb-3">
                <div class="flex-grow-1">
                  <?php if ($i === 0 && $a['virtual'] === null): ?>
                    <div class="fw-bold text-primary mb-1">
                      <i class="bi bi-1-circle-fill"></i> Use this one</div>
                  <?php endif; ?>
                  <div class="text-muted-sm">Point your phone's camera at the code,
                    or type this into the phone's browser</div>
                  <div class="fs-5 fw-bold text-break" style="font-family:Consolas,monospace">
                    <?= e($a['url']) ?></div>
                </div>
                <?php if ($a['proven']): ?>
                  <span class="badge bg-success">Working now</span>
                <?php elseif ($a['virtual'] !== null): ?>
                  <span class="badge bg-secondary">Probably not this one</span>
                <?php endif; ?>
              </div>

              <?php /* The code is the point of this page, so it is given room and
                       a white surround - a QR printed edge to edge on a coloured
                       background is the usual reason a camera will not read one. */ ?>
              <div class="d-flex flex-wrap align-items-end gap-3">
                <div class="text-center">
                  <div class="qr-target bg-white border rounded p-2 d-inline-block"
                       data-qr="<?= e($a['url']) ?>"></div>
                  <div class="text-muted-sm mt-1">
                    <i class="bi bi-camera"></i> Scan with the phone camera</div>
                </div>
                <button class="btn btn-sm btn-outline-secondary mb-4" type="button"
                        data-copy="<?= e($a['url']) ?>">
                  <i class="bi bi-clipboard"></i> Copy address</button>
              </div>
              <?php if ($a['virtual'] !== null): ?>
                <div class="text-muted-sm mt-2">
                  This address belongs to <?= e($a['virtual']) ?> on this laptop, not to the
                  shop Wi-Fi. A phone almost certainly cannot reach it &mdash; try the
                  other address first.
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>

          <?php if (count($addresses) > 1): ?>
            <p class="text-muted-sm">
              This laptop has more than one network address &mdash; try the first, and
              if the phone will not load it, try the next.
            </p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-body">
        <div class="fw-bold mb-2"><i class="bi bi-phone"></i> Keep it on the home screen</div>
        <ol class="text-muted-sm mb-0 ps-3">
          <li><strong>iPhone (Safari):</strong> Share &rarr; Add to Home Screen. It then
              opens without the browser bars.</li>
          <li><strong>Android (Chrome):</strong> menu &rarr; Add to Home screen. On a plain
              <code>http://</code> address this gives a shortcut, not a full app &mdash;
              Android reserves the full app for secure addresses.</li>
        </ol>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="fw-bold mb-2"><i class="bi bi-pin-angle"></i> Stop the address changing</div>
        <p class="text-muted-sm mb-2">
          The router hands this laptop a new address whenever it feels like it, which is
          why an address written on paper stops working. Two ways to settle it:
        </p>
        <ul class="text-muted-sm mb-0 ps-3">
          <li>Ask the router to always give this laptop the same address &mdash; in the
              router's admin page this is called a <em>DHCP reservation</em> or
              <em>static lease</em>, set against this laptop.</li>
          <li>Or simply reopen this page whenever the phone stops connecting. It always
              shows the address that is true right now.</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="fw-bold mb-2"><i class="bi bi-wrench"></i> If the phone cannot open it</div>
        <div class="row g-3 text-muted-sm">
          <div class="col-md-6">
            <div class="fw-semibold text-body">1. Is Windows blocking it?</div>
            <p class="mb-1">
              XAMPP normally asks to allow Apache the first time it starts, so the
              permission is often already there. Two things have to agree: there must be
              an inbound rule for Apache, and that rule must cover the profile
              &mdash; <em>Private</em> or <em>Public</em> &mdash; that Windows has put the
              shop Wi-Fi in. Check both in an <strong>Administrator</strong> PowerShell:
            </p>
            <pre class="bg-light border rounded p-2 mb-1" style="white-space:pre-wrap"><code>Get-NetConnectionProfile | Select Name,NetworkCategory
Get-NetFirewallRule -Direction Inbound -Action Allow -Enabled True |
  Where DisplayName -like "*Apache*" | Select DisplayName,Profile</code></pre>
            <p class="mb-0">
              If no Apache rule comes back, add one for the profile the Wi-Fi is actually
              in &mdash; <code>New-NetFirewallRule -DisplayName "Smart Stock (Apache 80)"
              -Direction Inbound -Action Allow -Protocol TCP -LocalPort 80 -Profile
              Private</code>. Prefer marking the shop Wi-Fi as Private and allowing only
              Private, rather than opening the Public profile: a rule on Public also
              applies in a caf&eacute;.
            </p>
          </div>
          <div class="col-md-6">
            <div class="fw-semibold text-body">2. Is Apache listening to the network at all?</div>
            <?php if ($listen): ?>
              <p class="mb-1">Apache is set to listen on:
                <?php foreach ($listen as $l): ?>
                  <code><?= e($l) ?></code>
                <?php endforeach; ?>
              </p>
              <?php if ($localOnly): ?>
                <div class="alert alert-danger py-2 mb-1">
                  Apache is listening only on this laptop itself. No phone can reach it
                  until that is changed to <code>Listen 80</code> in
                  <code>httpd.conf</code>, followed by a restart of Apache.
                </div>
              <?php else: ?>
                <p class="mb-1">That accepts connections from the network, so this is not
                  the problem.</p>
              <?php endif; ?>
            <?php else: ?>
              <p class="mb-1">Apache's configuration file could not be read from here, so
                this check was skipped.</p>
            <?php endif; ?>
            <div class="fw-semibold text-body mt-2">3. Still nothing?</div>
            <ul class="mb-0 ps-3">
              <li>Check the phone is on the shop Wi-Fi, not mobile data.</li>
              <li>Some routers have <em>AP isolation</em> or <em>guest mode</em> on, which
                  stops devices seeing each other. Turn it off, or move both onto the
                  main network.</li>
              <li>Apache and MySQL must both be started in the XAMPP Control Panel.</li>
            </ul>
          </div>
        </div>
        <hr>
        <div class="text-muted-sm">
          This laptop is <code><?= e($host) ?></code>. This page was served from
          <code><?= e($arrivedOn ?: 'unknown') ?></code><?php
            ?><?= $proven ? ' &mdash; which means you are reading it over the network, and it works.'
                          : ' &mdash; you are reading it on the laptop itself.' ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= base_url('assets/vendor/qrcode.js') ?>"></script>
<script>
(function () {
  document.querySelectorAll('.qr-target').forEach(function (node) {
    try {
      var qr = qrcode(0, 'M');
      qr.addData(node.dataset.qr);
      qr.make();
      node.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2, scalable: false });
      node.title = 'Scan with the phone camera: ' + node.dataset.qr;
    } catch (e) {
      node.textContent = '';
    }
  });
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var text = button.dataset.copy;
      var done = function () {
        var was = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check2"></i> Copied';
        setTimeout(function () { button.innerHTML = was; }, 1500);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function () {});
        return;
      }
      // Plain http:// has no clipboard API, which is exactly where this page lives.
      var box = document.createElement('textarea');
      box.value = text; box.setAttribute('readonly', '');
      box.style.position = 'fixed'; box.style.opacity = '0';
      document.body.appendChild(box); box.select();
      try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
      document.body.removeChild(box);
    });
  });
})();
</script>
