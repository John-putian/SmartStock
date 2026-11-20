<?php
/** FR-11b - email notification settings. Administrator only. */
$types = [
  'daily_income' => 'End-of-day takings',
  'stock_out'    => 'Sold out',
  'stock_low'    => 'Running low',
  'price_request'=> 'Price requests',
  'discount'     => 'Discounts given',
];
$on = (array) ($cfg['send_types'] ?? []);
?>

<div class="row g-3">
  <div class="col-lg-7">

    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-envelope-at"></i> Status</div>
      <div class="card-body">
        <?php if ($ready): ?>
          <div class="d-flex gap-3 align-items-start">
            <i class="bi bi-check-circle-fill text-success fs-3"></i>
            <div>
              <div class="fw-semibold">Email is switched on.</div>
              <div class="text-muted-sm">
                Sending as <span class="mono"><?= e($cfg['username']) ?></span>
                through <?= e($cfg['host']) ?>:<?= (int) $cfg['port'] ?>
                over <?= e(strtoupper($cfg['encryption'] ?? 'tls')) ?>.
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="d-flex gap-3 align-items-start">
            <i class="bi bi-exclamation-triangle-fill text-warning fs-3"></i>
            <div>
              <div class="fw-semibold">Not configured yet — nothing is being sent.</div>
              <div class="text-muted-sm">
                The bell inside the system is working normally. Only the email
                copy is waiting on the three lines below.
              </div>
            </div>
          </div>

          <hr>
          <p class="mb-2 fw-semibold">To switch it on</p>
          <ol class="text-muted-sm mb-0" style="line-height:1.9">
            <li>Turn on 2-Step Verification for the sending Gmail account
                (<span class="mono">myaccount.google.com → Security</span>).</li>
            <li>Go to <span class="mono">myaccount.google.com/apppasswords</span>,
                name it <em>Smart Stock</em>, and click <strong>Create</strong>.
                Google shows 16 letters.</li>
            <li>Open <span class="mono">config/mail.php</span> and fill in
                <span class="mono">username</span> (the Gmail address),
                <span class="mono">password</span> (those 16 letters) and set
                <span class="mono">enabled</span> to <span class="mono">true</span>.</li>
          </ol>
          <div class="alert alert-secondary mt-3 mb-0 text-muted-sm">
            <i class="bi bi-shield-lock"></i>
            An app password is a credential, so it is typed straight into the
            file rather than through this page — <span class="mono">config/</span>
            carries an <span class="mono">.htaccess</span> that denies the web
            access to it. Revoke it from the same Google page when it is no
            longer needed, and keep it out of any public repository.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-people"></i> Who is emailed</div>
      <div class="card-body pb-2">
        <p class="text-muted-sm">
          Only the office receives these. Leave an address blank and that
          person gets the bell alone.
        </p>

        <?php foreach ($recipients as $r): ?>
          <form method="post" action="<?= url('mail.address') ?>"
                class="row g-2 align-items-center mb-2">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int) $r['id'] ?>">
            <div class="col-sm-4">
              <div class="fw-semibold"><?= e($r['full_name']) ?></div>
              <span class="badge badge-soft-<?= role_class($r['role']) ?>">
                <?= e(role_label($r['role'])) ?></span>
            </div>
            <div class="col-sm-6">
              <input type="email" name="email" class="form-control form-control-sm"
                     placeholder="no address — bell only"
                     value="<?= e((string) ($r['email'] ?? '')) ?>">
            </div>
            <div class="col-sm-2 d-grid">
              <button class="btn btn-sm btn-outline-secondary">Save</button>
            </div>
          </form>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-send"></i> Send one now</div>
      <div class="card-body">
        <p class="text-muted-sm">
          Sends the real takings email for
          <?= $digest ? '<strong>' . e($digest['day']) . '</strong>' : 'the latest day on record' ?>,
          so the setup can be proved without waiting for tomorrow.
        </p>
        <form method="post" action="<?= url('mail.test') ?>" class="row g-2">
          <?= csrf_field() ?>
          <div class="col-sm-8">
            <input type="email" name="to" class="form-control" required
                   placeholder="somebody@example.com"
                   value="<?= e((string) ($recipients[0]['email'] ?? '')) ?>">
          </div>
          <div class="col-sm-4 d-grid">
            <button class="btn btn-primary" <?= $ready ? '' : 'disabled' ?>>
              <i class="bi bi-send"></i> Send</button>
          </div>
        </form>
        <?php if (!$ready): ?>
          <div class="text-muted-sm mt-2">
            <i class="bi bi-info-circle"></i>
            Greyed out until <span class="mono">config/mail.php</span> is filled in.
            The preview on the right works either way.
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-eye"></i> What it looks like</div>
      <div class="card-body">
        <?php if ($digest): ?>
          <p class="text-muted-sm">
            The takings email for <strong><?= e($digest['day']) ?></strong>,
            rendered from the real figures. Nothing leaves this computer when
            you open it.
          </p>
          <a class="btn btn-outline-secondary w-100" target="_blank"
             href="<?= url('mail.preview') ?>">
            <i class="bi bi-box-arrow-up-right"></i> Open the preview</a>
        <?php else: ?>
          <p class="text-muted mb-0">
            No sales on record yet, so there is nothing to summarise.
          </p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-funnel"></i> What gets emailed</div>
      <ul class="list-group list-group-flush">
        <?php foreach ($types as $key => $label):
              $isOn = in_array($key, $on, true); ?>
          <li class="list-group-item d-flex align-items-center">
            <i class="bi bi-<?= $isOn ? 'check-circle-fill text-success' : 'dash-circle text-secondary' ?> me-2"></i>
            <span class="flex-grow-1"><?= e($label) ?></span>
            <span class="badge badge-soft-<?= $isOn ? 'success' : 'secondary' ?>">
              <?= $isOn ? 'emailed' : 'bell only' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="card-body text-muted-sm">
        The shop asked for the takings and nothing else. A daily summary is
        worth an inbox; a stock warning is what the bell is for, and an inbox
        that fills with them stops being read. Change the list in
        <span class="mono">config/mail.php</span> under
        <span class="mono">send_types</span>.
      </div>
    </div>
  </div>
</div>

<div class="alert alert-info mt-3 no-print">
  <i class="bi bi-clock-history"></i>
  <strong>When it goes out.</strong> XAMPP has no scheduler, so the takings
  email is sent the first time anybody opens Smart Stock on a new day,
  covering the day before. A day with no sales produces no email, and the
  same day can never be sent twice.
</div>
