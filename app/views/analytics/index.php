<?php /** FR-09 - decision support: sales analytics. */ ?>

<ul class="nav nav-pills mb-3 no-print">
  <li class="nav-item"><a class="nav-link active" href="<?= url('analytics') ?>">
    <i class="bi bi-graph-up-arrow"></i> Sales Analytics</a></li>
  <li class="nav-item ms-auto">
    <form method="get" class="d-flex gap-2 align-items-center">
      <input type="hidden" name="page" value="analytics">
      <span class="small text-muted">Analysis window from</span>
      <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" style="width:150px">
      <button class="btn btn-sm btn-primary">Apply</button>
    </form>
  </li>
</ul>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Revenue in Window</div>
      <div class="k-value"><?= peso($summary['revenue'] ?? 0) ?></div>
      <div class="k-foot"><?= date('M j, Y', strtotime($from)) ?> &ndash; <?= date('M j, Y', strtotime($to)) ?></div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Transactions</div>
      <div class="k-value"><?= num($summary['txn'] ?? 0) ?></div>
      <div class="k-foot">Average <?= peso($summary['avg_sale'] ?? 0) ?> per sale</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Monthly Trend</div>
      <?php $slope = $proj['slope'] ?? 0; ?>
      <div class="k-value text-<?= $slope >= 0 ? 'success' : 'danger' ?>">
        <i class="bi bi-arrow-<?= $slope >= 0 ? 'up' : 'down' ?>-right"></i>
        <?= peso(abs($slope)) ?></div>
      <div class="k-foot">Average change per month (linear fit)</div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi"><div class="k-label">Next Month Projection</div>
      <div class="k-value text-primary"><?= peso($proj['proj_values'][0] ?? 0) ?></div>
      <div class="k-foot">Least-squares projection of revenue</div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header"><i class="bi bi-graph-up"></i> Monthly Revenue with 3-Month Projection
    <div class="text-muted-sm fw-normal mt-1">
      Solid bars are recorded revenue. The dashed line extends the least-squares trend of completed
      months forward, giving management an indicative target for stocking and cash planning.
    </div>
  </div>
  <div class="card-body"><div class="chart-box"><canvas id="projChart"></canvas></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-calendar-week"></i> Weekly Sales Momentum (last 12 weeks)</div>
      <div class="card-body"><div class="chart-box"><canvas id="weekChart"></canvas></div></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart"></i> Category Contribution</div>
      <div class="card-body"><div class="chart-box"><canvas id="catChart"></canvas></div></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-calendar3"></i> Performance by Day of Week
        <div class="text-muted-sm fw-normal mt-1">Useful for scheduling staff and planning deliveries.</div></div>
      <div class="card-body"><div class="chart-box sm"><canvas id="dowChart"></canvas></div></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-clock"></i> Peak Selling Hours
        <div class="text-muted-sm fw-normal mt-1">Shows when the store is busiest during opening hours.</div></div>
      <div class="card-body"><div class="chart-box sm"><canvas id="hourChart"></canvas></div></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-trophy"></i> Best Performing Items</div>
      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
          <thead><tr><th>#</th><th>Item</th><th class="text-center">Units</th><th class="text-end">Revenue</th></tr></thead>
          <tbody>
          <?php foreach ($top as $i => $t): ?>
            <tr><td class="text-muted"><?= $i + 1 ?></td>
              <td><a class="text-dark fw-semibold" href="<?= url('inventory.view', ['id' => $t['product_id']]) ?>">
                <?= e($t['product_name']) ?></a></td>
              <td class="text-center"><?= num($t['units']) ?></td>
              <td class="text-end fw-semibold"><?= peso($t['revenue']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-hourglass-split"></i> Slow-Moving Stock
        <div class="text-muted-sm fw-normal mt-1">
          In stock but with no sales in the last 60 days &ndash; candidates for markdown or bundling.
        </div></div>
      <div class="table-responsive">
        <table class="table table-hover table-sm mb-0">
          <thead><tr><th>Item</th><th>Category</th><th class="text-center">Qty</th>
            <th class="text-end">Tied-up cost</th><th>Last sold</th></tr></thead>
          <tbody>
          <?php if (!$slow): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Every stocked item has sold in the last 60 days.</td></tr>
          <?php endif; ?>
          <?php foreach ($slow as $s): ?>
            <tr>
              <td><a class="text-dark fw-semibold" href="<?= url('inventory.view', ['id' => $s['id']]) ?>">
                <?= e($s['name']) ?></a></td>
              <td class="text-muted-sm"><?= e($s['category_name'] ?? '—') ?></td>
              <td class="text-center"><?= num($s['quantity']) ?></td>
              <td class="text-end fw-semibold"><?= peso((float) $s['quantity'] * (float) $s['cost_price']) ?></td>
              <td class="text-muted-sm"><?= $s['last_sold'] ? date('M j, Y', strtotime($s['last_sold'])) : 'Never' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$pLabels = js_json(array_merge($proj['labels'], $proj['proj_labels']));
$pActual = js_json(array_merge($proj['values'], array_fill(0, count($proj['proj_labels']), null)));
// The projection line is anchored to the last COMPLETED month, so the partial
// current month does not drag the visible trend line down.
$n = count($proj['values']);
$anchor = $n - 1;
if ($n > 1 && !empty($proj['labels']) && end($proj['labels']) === date('Y-m')) {
    $anchor = $n - 2;
}
$projSeries = array_fill(0, $n, null);
if ($anchor >= 0) { $projSeries[$anchor] = round((float) $proj['values'][$anchor], 2); }
$pProj = js_json(array_merge($projSeries, $proj['proj_values']));

$wLabels = js_json(array_column($weekly, 'bucket'));
$wValues = js_json(array_map(fn($r) => round((float) $r['revenue'], 2), $weekly));
$cLabels = js_json(array_column($byCat, 'category'));
$cValues = js_json(array_map(fn($r) => round((float) $r['revenue'], 2), $byCat));
$dLabels = js_json(array_map(fn($r) => substr($r['dname'], 0, 3), $dow));
$dValues = js_json(array_map(fn($r) => round((float) $r['revenue'], 2), $dow));
$hLabels = js_json(array_map(fn($r) => date('g A', mktime((int) $r['hr'], 0)), $byHour));
$hValues = js_json(array_map(fn($r) => (int) $r['txn'], $byHour));

$inlineScript = <<<JS
new Chart(document.getElementById('projChart'), {
  data: { labels: {$pLabels}, datasets: [
    { type: 'bar', label: 'Recorded revenue', data: {$pActual},
      backgroundColor: 'rgba(196,17,20,.80)', borderRadius: 4 },
    { type: 'line', label: 'Projected revenue', data: {$pProj},
      borderColor: '#1f1d1c', borderDash: [6, 4], borderWidth: 2,
      pointRadius: 3, pointBackgroundColor: '#1f1d1c', tension: .2, fill: false,
      spanGaps: true } ] },
  options: { responsive: true, maintainAspectRatio: false,
    plugins: { tooltip: { callbacks: { label: function (c) {
      return c.dataset.label + ': ' + (c.parsed.y === null ? '—' : AA.peso(c.parsed.y)); } } } },
    scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return AA.peso(v); } } } } }
});

new Chart(document.getElementById('weekChart'), {
  type: 'line',
  data: { labels: {$wLabels}, datasets: [{ label: 'Weekly revenue', data: {$wValues},
    borderColor: '#2f7d4f', backgroundColor: 'rgba(47,125,79,.12)', fill: true,
    tension: .35, borderWidth: 2, pointRadius: 2 }] },
  options: { responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false },
      tooltip: { callbacks: { label: function (c) { return AA.peso(c.parsed.y); } } } },
    scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return AA.peso(v); } } },
              x: { ticks: { maxRotation: 45, autoSkip: true } } } }
});

new Chart(document.getElementById('catChart'), {
  type: 'polarArea',
  data: { labels: {$cLabels}, datasets: [{ data: {$cValues}, backgroundColor: AA.palette }] },
  options: { responsive: true, maintainAspectRatio: false,
    scales: { r: { ticks: { display: false } } },
    plugins: { legend: { position: 'right', labels: { boxWidth: 8, font: { size: 11 } } },
      tooltip: { callbacks: { label: function (c) { return c.label + ': ' + AA.peso(c.parsed.r); } } } } }
});

new Chart(document.getElementById('dowChart'), {
  type: 'bar',
  data: { labels: {$dLabels}, datasets: [{ label: 'Revenue', data: {$dValues},
    backgroundColor: '#2b4c7e', borderRadius: 4 }] },
  options: { responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false },
      tooltip: { callbacks: { label: function (c) { return AA.peso(c.parsed.y); } } } },
    scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return AA.peso(v); } } } } }
});

new Chart(document.getElementById('hourChart'), {
  type: 'bar',
  data: { labels: {$hLabels}, datasets: [{ label: 'Transactions', data: {$hValues},
    backgroundColor: '#b8860b', borderRadius: 4 }] },
  options: { responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});
JS;
?>
