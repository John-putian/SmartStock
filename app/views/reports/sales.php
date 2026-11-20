<?php
/** FR-07 - sales and revenue report. */
$targetPage  = 'reports.sales';
$showGroup   = true;
$exportWhat  = 'sales';
require APP_PATH . '/views/reports/_filter.php';

$days = max(1, (int) ((strtotime($p['to']) - strtotime($p['from'])) / 86400) + 1);
?>

<ul class="nav nav-pills mb-3 no-print">
  <li class="nav-item"><a class="nav-link active" href="<?= url('reports.sales', ['preset' => $p['preset']]) ?>">
    <i class="bi bi-cash-stack"></i> Sales &amp; Revenue</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= url('reports.inventory') ?>">
    <i class="bi bi-boxes"></i> Inventory Valuation</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= url('reports.movement', ['preset' => $p['preset']]) ?>">
    <i class="bi bi-arrow-left-right"></i> Stock Movement</a></li>
</ul>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Total Revenue</div>
      <div class="k-value"><?= peso($summary['revenue'] ?? 0) ?></div>
      <div class="k-foot">Across <?= num($days) ?> day(s)</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Transactions</div>
      <div class="k-value"><?= num($summary['txn'] ?? 0) ?></div>
      <div class="k-foot"><?= number_format(($summary['txn'] ?? 0) / $days, 1) ?> per day on average</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Average Sale Value</div>
      <div class="k-value"><?= peso($summary['avg_sale'] ?? 0) ?></div>
      <div class="k-foot">Per transaction</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Discounts Given</div>
      <div class="k-value text-warning"><?= peso($summary['discounts'] ?? 0) ?></div>
      <div class="k-foot">
        <?= (float) ($summary['revenue'] ?? 0) > 0
            ? number_format((float) $summary['discounts'] / (float) $summary['revenue'] * 100, 1) . '% of revenue'
            : '—' ?></div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header"><i class="bi bi-bar-chart-line"></i>
    Revenue Trend (<?= e(['day' => 'daily', 'week' => 'weekly', 'month' => 'monthly', 'year' => 'annual'][$p['group']] ?? 'daily') ?>)</div>
  <div class="card-body"><div class="chart-box"><canvas id="revChart"></canvas></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center"><i class="bi bi-trophy me-2"></i> Top Selling Items
        <a class="ms-auto small" href="<?= url('reports.export', ['preset' => $p['preset'], 'from' => $p['from'], 'to' => $p['to'], 'what' => 'products']) ?>">Export</a></div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>#</th><th>SKU</th><th>Item</th>
            <th class="text-center">Units</th><th class="text-end">Revenue</th></tr></thead>
          <tbody>
          <?php if (!$top): ?><tr><td colspan="5" class="text-center text-muted py-4">No sales in this period.</td></tr><?php endif; ?>
          <?php foreach ($top as $i => $t): ?>
            <tr><td class="text-muted"><?= $i + 1 ?></td>
              <td class="mono"><?= e($t['sku']) ?></td>
              <td class="fw-semibold"><?= e($t['product_name']) ?></td>
              <td class="text-center"><?= num($t['units']) ?></td>
              <td class="text-end fw-semibold"><?= peso($t['revenue']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-pie-chart"></i> Revenue by Category</div>
      <div class="card-body"><div class="chart-box sm"><canvas id="catChart"></canvas></div></div>
    </div>
    <div class="card">
      <div class="card-header"><i class="bi bi-person-badge"></i> Sales by Cashier</div>
      <table class="table table-sm mb-0">
        <thead><tr><th>Cashier</th><th class="text-center">Txn</th><th class="text-end">Revenue</th></tr></thead>
        <tbody>
        <?php foreach ($byCash as $c): ?>
          <tr><td><?= e($c['cashier']) ?></td>
            <td class="text-center"><?= num($c['txn']) ?></td>
            <td class="text-end fw-semibold"><?= peso($c['revenue']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="card-header border-top rounded-0"><i class="bi bi-credit-card"></i> Payment Methods</div>
      <table class="table table-sm mb-0">
        <tbody>
        <?php foreach ($byPay as $m): ?>
          <tr><td><?= e($m['payment_method']) ?></td>
            <td class="text-center"><?= num($m['txn']) ?></td>
            <td class="text-end fw-semibold"><?= peso($m['revenue']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap align-items-center gap-2">
    <span><i class="bi bi-receipt"></i> Transaction Log</span>
    <span class="text-muted-sm">&middot; <?= num(count($sales)) ?> record(s)<?= count($sales) >= 500 ? ' (showing the 500 most recent)' : '' ?></span>
    <form class="ms-auto no-print" method="get">
      <input type="hidden" name="page" value="reports.sales">
      <input type="hidden" name="preset" value="<?= e($p['preset']) ?>">
      <input type="hidden" name="from" value="<?= e($p['from']) ?>">
      <input type="hidden" name="to" value="<?= e($p['to']) ?>">
      <div class="input-group input-group-sm" style="width:260px">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input name="q" value="<?= e($q) ?>" class="form-control" placeholder="Invoice or customer">
      </div>
    </form>
  </div>
  <div class="table-responsive" style="max-height:520px;overflow:auto">
    <table class="table table-hover mb-0">
      <thead class="position-sticky top-0"><tr><th>Invoice</th><th>Date &amp; time</th><th>Customer</th>
        <th>Cashier</th><th>Payment</th><th class="text-center">Items</th>
        <th class="text-end">Discount</th><th class="text-end">Total</th><th class="no-print"></th></tr></thead>
      <tbody>
      <?php if (!$sales): ?><tr><td colspan="9" class="text-center text-muted py-5">No transactions in this period.</td></tr><?php endif; ?>
      <?php foreach ($sales as $s): ?>
        <tr>
          <td class="mono"><?= e($s['invoice_no']) ?></td>
          <td class="text-nowrap text-muted-sm"><?= date('M j, Y g:i A', strtotime($s['sale_date'])) ?></td>
          <td><?= e($s['customer_name'] ?: 'Walk-in') ?></td>
          <td class="text-muted-sm"><?= e($s['cashier'] ?? '—') ?></td>
          <td><span class="badge badge-soft-secondary"><?= e($s['payment_method']) ?></span></td>
          <td class="text-center"><?= num($s['units']) ?></td>
          <td class="text-end text-muted">
            <?php if ((float) $s['discount'] > 0): ?>
              <?= peso($s['discount']) ?>
              <?php if ((float) ($s['discount_percent'] ?? 0) > 0): ?>
                <div class="text-muted-sm"><?= rtrim(rtrim(number_format((float) $s['discount_percent'], 2, '.', ''), '0'), '.') ?>% off</div>
              <?php endif; ?>
              <?php /* Who let the money go. This is the column to scan when
                       checking that nothing was marked down unauthorised. */ ?>
              <div class="text-muted-sm">
                <?php if (!empty($s['discount_approver'])): ?>
                  <i class="bi bi-patch-check text-success"></i>
                  <?= e($s['discount_approver']) ?>
                <?php else: ?>
                  <i class="bi bi-question-circle text-warning"></i> not recorded
                <?php endif; ?>
              </div>
            <?php else: ?>&mdash;<?php endif; ?>
          </td>
          <td class="text-end fw-semibold"><?= peso($s['total']) ?></td>
          <td class="no-print"><a class="btn btn-sm btn-outline-secondary py-0"
             href="<?= url('pos.receipt', ['id' => $s['id']]) ?>"><i class="bi bi-receipt"></i></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$tLabels = js_json(array_column($trend, 'bucket'));
$tValues = js_json(array_map(fn($r) => round((float) $r['revenue'], 2), $trend));
$tTxn    = js_json(array_map(fn($r) => (int) $r['txn'], $trend));
$cLabels = js_json(array_column($byCat, 'category'));
$cValues = js_json(array_map(fn($r) => round((float) $r['revenue'], 2), $byCat));

$inlineScript = <<<JS
new Chart(document.getElementById('revChart'), {
  data: { labels: {$tLabels},
    datasets: [
      { type: 'bar', label: 'Revenue', data: {$tValues}, yAxisID: 'y',
        backgroundColor: 'rgba(196,17,20,.78)', borderRadius: 3, order: 2 },
      { type: 'line', label: 'Transactions', data: {$tTxn}, yAxisID: 'y1',
        borderColor: '#1f1d1c', backgroundColor: '#1f1d1c', borderWidth: 2,
        pointRadius: 2, tension: .3, order: 1 }
    ] },
  options: { responsive: true, maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: { tooltip: { callbacks: { label: function (c) {
      return c.dataset.label + ': ' + (c.datasetIndex === 0 ? AA.peso(c.parsed.y) : c.parsed.y);
    } } } },
    scales: {
      y:  { beginAtZero: true, position: 'left',
            ticks: { callback: function (v) { return AA.peso(v); } },
            title: { display: true, text: 'Revenue' } },
      y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false },
            ticks: { precision: 0 }, title: { display: true, text: 'Transactions' } },
      x:  { ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 20 } } } }
});

new Chart(document.getElementById('catChart'), {
  type: 'doughnut',
  data: { labels: {$cLabels},
    datasets: [{ data: {$cValues}, backgroundColor: AA.palette, borderWidth: 2, borderColor: '#fff' }] },
  options: { responsive: true, maintainAspectRatio: false, cutout: '55%',
    plugins: { legend: { position: 'right', labels: { boxWidth: 8, font: { size: 11 } } },
      tooltip: { callbacks: { label: function (c) { return c.label + ': ' + AA.peso(c.parsed); } } } } }
});
JS;
?>
