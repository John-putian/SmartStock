<?php
/** FR-08 - audit trail. */
$toneOf = function (string $a): string {
    if (str_contains($a, 'DELETE') || str_contains($a, 'FAILED')) return 'danger';
    if (str_contains($a, 'CREATE')) return 'success';
    if (str_contains($a, 'UPDATE') || str_contains($a, 'ADJUST')) return 'warning';
    if (str_contains($a, 'LOGIN') || str_contains($a, 'LOGOUT')) return 'info';
    return 'secondary';
};
?>

<div class="alert alert-light border d-flex gap-2 no-print">
  <i class="bi bi-shield-check text-primary fs-5"></i>
  <div class="small">
    <strong>Accountability record.</strong> Every sign-in, inventory change, sale and export is written
    here with the responsible account, so any transaction or stock modification can be traced back
    to the user who made it.
  </div>
</div>

<div class="card mb-3 no-print">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="page" value="audit">
      <div class="col-6 col-lg-2">
        <label class="form-label small fw-semibold mb-1">From</label>
        <input type="date" name="from" value="<?= e($f['from']) ?>" class="form-control form-control-sm">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label small fw-semibold mb-1">To</label>
        <input type="date" name="to" value="<?= e($f['to']) ?>" class="form-control form-control-sm">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label small fw-semibold mb-1">Module</label>
        <select name="module" class="form-select form-select-sm">
          <option value="">All modules</option>
          <?php foreach ($modules as $m): ?>
            <option value="<?= e($m['module']) ?>" <?= $f['module'] === $m['module'] ? 'selected' : '' ?>>
              <?= e($m['module']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label small fw-semibold mb-1">User</label>
        <select name="user" class="form-select form-select-sm">
          <option value="">All users</option>
          <?php foreach ($users as $u2): ?>
            <option value="<?= $u2['id'] ?>" <?= (string) $f['user'] === (string) $u2['id'] ? 'selected' : '' ?>>
              <?= e($u2['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-3">
        <label class="form-label small fw-semibold mb-1">Search</label>
        <input name="q" value="<?= e($f['q']) ?>" class="form-control form-control-sm"
               placeholder="Action or description">
      </div>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-9">
    <div class="card">
      <div class="card-header"><i class="bi bi-list-columns"></i> Activity Log
        <span class="text-muted-sm">&middot; <?= num(count($logs)) ?> entr(y/ies)<?= count($logs) >= 400 ? ', showing the 400 most recent' : '' ?></span></div>
      <div class="table-responsive" style="max-height:640px;overflow:auto">
        <table class="table table-hover table-sm mb-0">
          <thead class="position-sticky top-0"><tr><th>Date &amp; time</th><th>User</th>
            <th>Module</th><th>Action</th><th>Description</th><th>IP address</th></tr></thead>
          <tbody>
          <?php if (!$logs): ?>
            <tr><td colspan="6" class="text-center text-muted py-5">No audit entries for the selected filters.</td></tr>
          <?php endif; ?>
          <?php foreach ($logs as $l): ?>
            <tr>
              <td class="text-nowrap text-muted-sm"><?= date('M j, Y g:i A', strtotime($l['created_at'])) ?></td>
              <td><?= e($l['full_name'] ?? $l['username'] ?? 'System') ?></td>
              <td class="text-muted-sm"><?= e($l['module']) ?></td>
              <td><span class="badge badge-soft-<?= $toneOf($l['action']) ?>"><?= e($l['action']) ?></span></td>
              <td><?= e($l['description']) ?></td>
              <td class="mono text-muted-sm"><?= e($l['ip_address']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-3">
    <div class="card">
      <div class="card-header"><i class="bi bi-bar-chart"></i> Activity by Action</div>
      <div class="list-group list-group-flush">
        <?php $max = max(1, (int) ($byAction[0]['n'] ?? 1));
              foreach ($byAction as $a): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between small">
              <span class="fw-semibold"><?= e($a['action']) ?></span>
              <span class="text-muted"><?= num($a['n']) ?></span>
            </div>
            <div class="progress progress-thin mt-1">
              <div class="progress-bar" style="width:<?= (int) ($a['n'] / $max * 100) ?>%"></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$byAction): ?>
          <div class="p-4 text-center text-muted">No activity recorded.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
