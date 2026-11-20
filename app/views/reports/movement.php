<?php
/** FR-07 - inventory movement report. */
$targetPage = 'reports.movement';
$showGroup  = false;
$exportWhat = 'movement';
$extraFilter = '<div class="col-6 col-lg-2">'
  . '<label class="form-label small fw-semibold mb-1">Movement type</label>'
  . '<select name="type" class="form-select form-select-sm">'
  . '<option value="">All types</option>'
  . '<option value="in"' . ($type === 'in' ? ' selected' : '') . '>Stock in</option>'
  . '<option value="out"' . ($type === 'out' ? ' selected' : '') . '>Stock out (sales)</option>'
  . '<option value="adjust"' . ($type === 'adjust' ? ' selected' : '') . '>Adjustments</option>'
  . '</select></div>';
require APP_PATH . '/views/reports/_filter.php';

$sum = ['in' => 0, 'out' => 0, 'adjust' => 0];
foreach ($totals as $t) { $sum[$t['type']] = (int) $t['units']; }
?>

<ul class="nav nav-pills mb-3 no-print">
  <li class="nav-item"><a class="nav-link" href="<?= url('reports.sales', ['preset' => $p['preset']]) ?>">
    <i class="bi bi-cash-stack"></i> Sales &amp; Revenue</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= url('reports.inventory') ?>">
    <i class="bi bi-boxes"></i> Inventory Valuation</a></li>
  <li class="nav-item"><a class="nav-link active" href="<?= url('reports.movement', ['preset' => $p['preset']]) ?>">
    <i class="bi bi-arrow-left-right"></i> Stock Movement</a></li>
</ul>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="kpi d-flex justify-content-between">
      <div><div class="k-label">Units Received</div>
        <div class="k-value text-success">+<?= num($sum['in']) ?></div>
        <div class="k-foot">Deliveries and restocks</div></div>
      <div class="k-icon i-green"><i class="bi bi-box-arrow-in-down"></i></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="kpi d-flex justify-content-between">
      <div><div class="k-label">Units Sold Out</div>
        <div class="k-value text-danger">&minus;<?= num($sum['out']) ?></div>
        <div class="k-foot">Deducted by POS transactions</div></div>
      <div class="k-icon i-red"><i class="bi bi-box-arrow-up"></i></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="kpi d-flex justify-content-between">
      <div><div class="k-label">Adjustment Units</div>
        <div class="k-value text-warning">&plusmn;<?= num($sum['adjust']) ?></div>
        <div class="k-foot">Physical count corrections</div></div>
      <div class="k-icon i-amber"><i class="bi bi-pencil-square"></i></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-clock-history"></i> Movement Ledger
    <span class="text-muted-sm">&middot; <?= num(count($rows)) ?> entr(y/ies)<?= count($rows) >= 500 ? ', showing the 500 most recent' : '' ?></span></div>
  <div class="table-responsive" style="max-height:640px;overflow:auto">
    <table class="table table-hover table-sm mb-0">
      <thead class="position-sticky top-0"><tr><th>Date &amp; time</th><th>SKU</th><th>Item</th>
        <th>Type</th><th class="text-center">Qty</th><th class="text-center">Balance</th>
        <th>Reference</th><th>Recorded by</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="text-center text-muted py-5">No stock movements in this period.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $m):
            $tone = $m['type'] === 'in' ? 'success' : ($m['type'] === 'out' ? 'danger' : 'warning');
            $sign = $m['type'] === 'in' ? '+' : ($m['type'] === 'out' ? '−' : '±'); ?>
        <tr>
          <td class="text-nowrap text-muted-sm"><?= date('M j, Y g:i A', strtotime($m['created_at'])) ?></td>
          <td class="mono"><?= e($m['sku']) ?></td>
          <td><a class="text-dark" href="<?= url('inventory.view', ['id' => $m['product_id']]) ?>"><?= e($m['name']) ?></a></td>
          <td><span class="badge badge-soft-<?= $tone ?>"><?= strtoupper(e($m['type'])) ?></span></td>
          <td class="text-center fw-semibold text-<?= $tone ?>"><?= $sign . num($m['quantity']) ?></td>
          <td class="text-center"><?= num($m['balance']) ?></td>
          <td class="mono text-muted-sm"><?= e($m['reference']) ?></td>
          <td class="text-muted-sm"><?= e($m['full_name'] ?? 'System') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
