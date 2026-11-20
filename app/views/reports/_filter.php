<?php
/** Shared reporting-period selector. Expects $p and $targetPage. */
$presets = [
    'today'     => 'Today',
    'yesterday' => 'Yesterday',
    'week'      => 'This week',
    'last7'     => 'Last 7 days',
    'month'     => 'This month',
    'lastmonth' => 'Last month',
    'last30'    => 'Last 30 days',
    'quarter'   => 'Last 90 days',
    'year'      => 'This year',
    'custom'    => 'Custom range…',
];
?>
<div class="card mb-3 no-print">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="page" value="<?= e($targetPage) ?>">
      <div class="col-6 col-lg-3">
        <label class="form-label small fw-semibold mb-1">Reporting period</label>
        <select name="preset" class="form-select form-select-sm"
                onchange="document.getElementById('customRange').style.display = this.value==='custom' ? 'flex':'none'">
          <?php foreach ($presets as $k => $v): ?>
            <option value="<?= $k ?>" <?= $p['preset'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-lg-4 row g-2" id="customRange"
           style="display:<?= $p['preset'] === 'custom' ? 'flex' : 'none' ?>">
        <div class="col-6">
          <label class="form-label small fw-semibold mb-1">From</label>
          <input type="date" name="from" value="<?= e($p['from']) ?>" class="form-control form-control-sm">
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold mb-1">To</label>
          <input type="date" name="to" value="<?= e($p['to']) ?>" class="form-control form-control-sm">
        </div>
      </div>

      <?php if (($showGroup ?? false)): ?>
        <div class="col-6 col-lg-2">
          <label class="form-label small fw-semibold mb-1">Group by</label>
          <select name="group" class="form-select form-select-sm">
            <?php foreach (['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Annual'] as $k => $v): ?>
              <option value="<?= $k ?>" <?= $p['group'] === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <?php if (!empty($extraFilter)) { echo $extraFilter; } ?>

      <div class="col-auto">
        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Apply</button>
      </div>
      <div class="col ms-auto text-end">
        <a class="btn btn-sm btn-outline-success"
           href="<?= url('reports.export', ['preset' => $p['preset'], 'from' => $p['from'],
                                            'to' => $p['to'], 'what' => $exportWhat ?? 'sales']) ?>">
          <i class="bi bi-file-earmark-excel"></i> Export Excel</a>
        <a class="btn btn-sm btn-outline-secondary" target="_blank"
           href="<?= url('reports.print', ['preset' => $p['preset'], 'from' => $p['from'], 'to' => $p['to']]) ?>">
          <i class="bi bi-printer"></i> Print</a>
      </div>
    </form>
    <div class="text-muted-sm mt-2">
      <i class="bi bi-calendar3"></i> Showing
      <strong><?= date('F j, Y', strtotime($p['from'])) ?></strong> to
      <strong><?= date('F j, Y', strtotime($p['to'])) ?></strong>
    </div>
  </div>
</div>
