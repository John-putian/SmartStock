<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in &middot; <?= e(APP_SHORT) ?></title>
<link rel="icon" href="<?= base_url('assets/img/favicon.png') ?>" type="image/png">
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
</head>
<body>
<div class="login-wrap">

  <section class="login-hero">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:26px">
      <img class="hero-logo" src="<?= base_url('assets/img/logo.png') ?>" alt="Smart Stock — A&amp;A Surplus">
      <div>
        <div style="font-weight:800;letter-spacing:.16em;text-transform:uppercase;font-size:1.02rem">Smart Stock</div>
        <div style="color:var(--aa-red);font-weight:700;letter-spacing:.2em;font-size:.8rem;text-transform:uppercase">A&amp;A Surplus</div>
        <div style="color:#9d938c;font-size:.82rem;margin-top:4px">Panabo City, Davao del Norte</div>
      </div>
    </div>

    <h1>Inventory, Sales &amp;<br>Decision Support System</h1>
    <p style="color:#b9afa8;max-width:460px;margin-top:12px">
      A centralised web platform that keeps stock records synchronised with every sale,
      warns you before items run out, and shows the office what to ask for in the
      next container.
    </p>

    <div style="margin-top:26px;max-width:460px">
      <div class="feat"><i class="bi bi-box-seam"></i>
        <div><strong>Real-time inventory</strong><br>
        <span style="color:#9d938c;font-size:.87rem">Stock is deducted the moment a sale is completed.</span></div></div>
      <div class="feat"><i class="bi bi-bell"></i>
        <div><strong>Automatic low-stock alerts</strong><br>
        <span style="color:#9d938c;font-size:.87rem">Reorder warnings before popular items run out.</span></div></div>
      <div class="feat"><i class="bi bi-graph-up-arrow"></i>
        <div><strong>Sales by category</strong><br>
        <span style="color:#9d938c;font-size:.87rem">What sold, what did not, and what the next container should carry.</span></div></div>
    </div>

    <div style="margin-top:auto;padding-top:34px;color:#7c726b;font-size:.78rem">
      IT 307 &ndash; Web Systems and Technologies 2 &middot;
      Duque &middot; Dizon &middot; Puti-an
    </div>
  </section>

  <section class="login-card">
    <div class="inner">
      <div class="text-center d-lg-none mb-3">
        <img class="mark-sm" src="<?= base_url('assets/img/logo.png') ?>" alt="Smart Stock — A&amp;A Surplus">
      </div>
      <h2 class="h4 fw-bold mb-1">Sign in to your account</h2>
      <p class="text-muted-sm mb-4">Access is role-based and every action is recorded in the audit trail.</p>

      <?php foreach (take_flash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> py-2"><?= $f['message'] ?></div>
      <?php endforeach; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 d-flex gap-2">
          <i class="bi bi-exclamation-octagon"></i><div><?= e($error) ?></div>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= url('login') ?>" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="loginUsername">Username</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input id="loginUsername" autocomplete="username" name="username" class="form-control" value="<?= e($username) ?>"
                   placeholder="e.g. admin" required autofocus>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold" for="loginPassword">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input id="loginPassword" autocomplete="current-password" name="password" type="password" class="form-control" placeholder="Enter password" required>
            <button aria-label="Show or hide password" class="btn btn-outline-secondary" type="button"
                    onclick="var i=this.parentNode.querySelector('input');i.type=i.type==='password'?'text':'password'">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <button class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="bi bi-box-arrow-in-right"></i> Sign in
        </button>
      </form>

      <?php if (defined('DEMO_MODE') && DEMO_MODE): ?>
      <style>
      .demo-panel{border:1px solid #e6ded7;border-radius:14px;background:#fbf8f6;padding:14px 16px}
      .demo-head{display:flex;align-items:center;gap:8px;font-weight:700;font-size:.9rem;color:#4a403a;margin-bottom:10px}
      .demo-head .demo-hint{margin-left:auto;font-weight:500;font-size:.76rem;color:#9d938c}
      .demo-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
      .demo-btn{display:flex;flex-direction:column;align-items:flex-start;gap:2px;border:1px solid #e2d8d0;
        background:#fff;border-radius:11px;padding:10px 12px;text-align:left;cursor:pointer;transition:.15s}
      .demo-btn:hover{border-color:var(--aa-red,#c0392b);box-shadow:0 2px 10px rgba(0,0,0,.07);transform:translateY(-1px)}
      .demo-btn i{font-size:1.05rem;color:var(--aa-red,#c0392b)}
      .demo-role{font-weight:700;font-size:.86rem;color:#2f2a27}
      .demo-user{font-size:.76rem;color:#9d938c;font-family:ui-monospace,Menlo,Consolas,monospace}
      .demo-btn.is-busy{opacity:.55;pointer-events:none}
      .demo-foot{margin-top:10px;font-size:.74rem;color:#9d938c;line-height:1.45}
      .demo-foot .mono{font-family:ui-monospace,Menlo,Consolas,monospace;color:#6b615a}
      @media (max-width:420px){.demo-grid{grid-template-columns:1fr}}
      </style>
      <div class="demo-panel mt-4">
        <div class="demo-head">
          <i class="bi bi-play-circle"></i><span>Demonstration mode</span>
          <span class="demo-hint">one click signs you in</span>
        </div>
        <div class="demo-grid">
          <?php foreach ([
            ['admin',   'admin',   'admin123',   'Administrator', 'bi-shield-lock'],
            ['manager', 'manager', 'manager123', 'Store Manager', 'bi-clipboard-check'],
            ['staff',   'staff',   'staff123',   'Work Staff',    'bi-box-seam'],
            ['cashier', 'cashier', 'cashier123', 'Cashier',       'bi-cash-coin'],
          ] as [$role, $user, $pass, $label, $icon]): ?>
            <button type="button" class="demo-btn" data-user="<?= e($user) ?>" data-pass="<?= e($pass) ?>">
              <i class="bi <?= e($icon) ?>"></i>
              <span class="demo-role"><?= e($label) ?></span>
              <span class="demo-user"><?= e($user) ?></span>
            </button>
          <?php endforeach; ?>
        </div>
        <div class="demo-foot">
          Each button fills this same form and submits it, so the password is checked exactly
          as it is for anyone else. Delete <span class="mono">DEMO-MODE-ON</span> in the
          project folder to hide this panel.
        </div>
      </div>
      <script>
      (function () {
        var form = document.querySelector('form[method="post"]');
        document.querySelectorAll('.demo-btn').forEach(function (b) {
          b.addEventListener('click', function () {
            document.getElementById('loginUsername').value = b.dataset.user;
            document.getElementById('loginPassword').value = b.dataset.pass;
            b.classList.add('is-busy');
            form.submit();
          });
        });
      })();
      </script>
      <?php endif; ?>
    </div>
  </section>
</div>
<script src="<?= base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
