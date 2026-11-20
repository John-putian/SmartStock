<?php /** FR-01 - user accounts and role-based access. */ ?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-people"></i> User Accounts
        <span class="text-muted-sm">&middot; <?= num(count($users)) ?> account(s)</span></div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Name</th><th>Username</th><th>Role</th><th>Contact</th>
            <th class="text-center">Sales</th><th>Last sign-in</th><th>Status</th>
            <th class="text-end no-print">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($users as $u2): ?>
            <tr class="<?= $u2['status'] === 'inactive' ? 'opacity-50' : '' ?>">
              <td class="fw-semibold"><?= e($u2['full_name']) ?>
                <?php if ((int) $u2['id'] === Auth::id()): ?>
                  <span class="badge badge-soft-info ms-1">You</span><?php endif; ?></td>
              <td class="mono"><?= e($u2['username']) ?></td>
              <td>
                <span class="badge badge-soft-<?= role_class($u2['role']) ?>">
                  <?= e(role_label($u2['role'])) ?></span>
              </td>
              <td class="text-muted-sm"><?= e($u2['contact_no'] ?: '—') ?></td>
              <td class="text-center"><?= num($u2['sales_count']) ?></td>
              <td class="text-muted-sm">
                <?= $u2['last_login'] ? date('M j, Y g:i A', strtotime($u2['last_login'])) : 'Never' ?></td>
              <td><span class="badge badge-soft-<?= $u2['status'] === 'active' ? 'success' : 'secondary' ?>">
                <?= ucfirst(e($u2['status'])) ?></span></td>
              <td class="text-end no-print">
                <button class="btn btn-sm btn-outline-secondary"
                        onclick='editUser(<?= js_json($u2, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                  <i class="bi bi-pencil"></i></button>
                <?php if ((int) $u2['id'] !== Auth::id() && $u2['status'] === 'active'): ?>
                  <form method="post" action="<?= url('users.delete', ['id' => $u2['id']]) ?>" class="d-inline">
<?= csrf_field() ?>
<button type="submit" class="btn btn-sm btn-outline-danger" 
                     onclick="return AA.confirmLink(this,'Deactivate this account? The user will no longer be able to sign in.')">
                    <i class="bi bi-person-dash"></i></button></form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header"><i class="bi bi-shield-lock"></i> What each role may do</div>
      <div class="card-body border-bottom py-2">
        <div class="row g-2 text-muted-sm">
          <?php foreach (Auth::ROLES as $r): ?>
            <div class="col-sm-6 d-flex gap-2">
              <span class="badge badge-soft-<?= role_class($r) ?> align-self-start">
                <?= e(role_label($r)) ?></span>
              <span><?= e(role_summary($r)) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Module</th>
            <?php foreach (Auth::ROLES as $r): ?>
              <th class="text-center"><?= e(role_label($r)) ?></th>
            <?php endforeach; ?>
          </tr></thead>
          <tbody>
          <?php
          /* Columns follow Auth::ROLES: admin, manager, staff, cashier.
             "~" means allowed, but only with an approval. */
          $matrix = [
            'Dashboard'                       => [true,  true,  true,  true],
            'Point of Sale &amp; receipts'    => [false, false, false, true],
            'View inventory &amp; QR labels'  => [true,  true,  true,  true],
            'Stock alerts'                    => [true,  true,  true,  true],
            'Receive / adjust stock'          => [true,  true,  true,  true],
            'Add an item &amp; set its price' => [true,  true,  true,  true],
            'Edit an item already listed'     => [true,  true,  true,  true],
            'Raise a listed price'            => [true,  true,  true,  '~'],
            'Lower a listed price'            => [true,  true,  '~',   '~'],
            'Give a discount at the till'     => [true,  true,  '~',   '~'],
            'Approve price drops &amp; discounts' => [true, true, false, false],
            'Archive an item'                 => [true,  true,  false, false],
            'Reports &amp; Sales by Category' => [true,  true,  false, false],
            'Sales analytics'                 => [true,  true,  false, false],
            'Manage categories'               => [true,  true,  false, false],
            'Audit trail'                     => [true,  true,  false, false],
            'Manage user accounts'            => [true,  false, false, false],
          ];
          foreach ($matrix as $label => $perm): ?>
            <tr><td><?= $label ?></td>
              <?php foreach ($perm as $allowed): ?>
                <td class="text-center">
                  <?php if ($allowed === '~'): ?>
                    <i class="bi bi-shield-lock text-warning"
                       title="Allowed only with an administrator or manager approval"></i>
                  <?php else: ?>
                    <i class="bi bi-<?= $allowed ? 'check-circle-fill text-success' : 'x-circle text-muted' ?>"></i>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-body py-2 text-muted-sm">
        <i class="bi bi-shield-lock text-warning"></i>
        means the action is possible but needs an administrator or manager to
        sign it off &mdash; a price change waits in Price Approvals, and a
        discount is authorised at the till before the sale is recorded.
        Anyone on the floor can add a new item and set its opening price; it is
        changing a price already on the shelf that is controlled.
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card" style="position:sticky;top:88px">
      <div class="card-header" id="formTitle"><i class="bi bi-person-plus"></i> Add User Account</div>
      <form class="card-body" method="post" action="<?= url('users.save') ?>" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="id" id="uId" value="">
        <div class="mb-3">
          <label class="form-label fw-semibold">Full name <span class="text-danger">*</span></label>
          <input name="full_name" id="uName" class="form-control" required placeholder="e.g. Juan Dela Cruz">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
          <input name="username" id="uUser" class="form-control mono" required placeholder="e.g. jdelacruz">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Password <span class="text-danger" id="pwReq">*</span></label>
          <input name="password" id="uPass" type="password" class="form-control" placeholder="At least 6 characters">
          <div class="form-text" id="pwHint">Passwords are stored as bcrypt hashes, never in plain text.</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Role</label>
          <select name="role" id="uRole" class="form-select" onchange="roleHint()">
            <?php foreach (Auth::ROLES as $r): ?>
              <option value="<?= e($r) ?>"<?= $r === 'cashier' ? ' selected' : '' ?>>
                <?= e(role_label($r)) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text" id="roleHint"><?= e(role_summary('cashier')) ?></div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Contact number</label>
          <input name="contact_no" id="uContact" class="form-control" placeholder="Optional">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" id="uStatus" class="form-select">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <button class="btn btn-primary w-100"><i class="bi bi-save"></i> Save Account</button>
        <button type="button" class="btn btn-link w-100 mt-1 text-muted" onclick="resetUser()">Clear form</button>
      </form>
    </div>
  </div>
</div>

<?php
$roleHints = js_json(array_combine(
    Auth::ROLES,
    array_map('role_summary', Auth::ROLES)
), JSON_UNESCAPED_UNICODE);

$inlineScript = <<<JS
var ROLE_HINTS = {$roleHints};

function roleHint() {
  var sel = document.getElementById('uRole');
  document.getElementById('roleHint').textContent = ROLE_HINTS[sel.value] || '';
}

function editUser(u) {
  document.getElementById('uId').value = u.id;
  document.getElementById('uName').value = u.full_name;
  document.getElementById('uUser').value = u.username;
  document.getElementById('uRole').value = u.role;
  roleHint();
  document.getElementById('uContact').value = u.contact_no || '';
  document.getElementById('uStatus').value = u.status;
  document.getElementById('uPass').value = '';
  document.getElementById('pwReq').style.display = 'none';
  document.getElementById('pwHint').textContent = 'Leave blank to keep the current password.';
  document.getElementById('formTitle').textContent = 'Edit Account: ' + u.username;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetUser() {
  document.querySelector('#uId').value = '';
  ['uName','uUser','uPass','uContact'].forEach(function (id) { document.getElementById(id).value = ''; });
  document.getElementById('uRole').value = 'cashier';
  roleHint();
  document.getElementById('uStatus').value = 'active';
  document.getElementById('pwReq').style.display = '';
  document.getElementById('pwHint').textContent = 'Passwords are stored as bcrypt hashes, never in plain text.';
  document.getElementById('formTitle').innerHTML = '<i class="bi bi-person-plus"></i> Add User Account';
}
JS; ?>
