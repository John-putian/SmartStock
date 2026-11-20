<?php
/**
 * Shop-floor dashboard for cashiers and work staff.
 *
 * Deliberately not a miniature of the manager's dashboard. It answers the two
 * questions the floor actually has - what came in, what went out - plus the
 * till the person in front of it has run today. No month totals, no growth
 * rate, no category mix: that is Insights, and Insights is the office's.
 */
$c = $counts;
?>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="kpi">
      <div class="k-label"><i class="bi bi-receipt"></i> My sales today</div>
      <div class="k-value"><?= num($mine['txn']) ?></div>
      <div class="k-foot"><?= peso($mine['revenue']) ?> rung up at my till</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi">
      <div class="k-label"><i class="bi bi-box-arrow-right"></i> Pieces out today</div>
      <div class="k-value"><?= num($soldToday) ?></div>
      <div class="k-foot">Sold across the whole shop</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi">
      <div class="k-label"><i class="bi bi-boxes"></i> Pieces on the floor</div>
      <div class="k-value"><?= num($c['units'] ?? 0) ?></div>
      <div class="k-foot"><?= num($c['total'] ?? 0) ?> item(s) listed</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="kpi">
      <div class="k-label"><i class="bi bi-exclamation-triangle"></i> Running low</div>
      <div class="k-value <?= $alertTotal ? 'text-warning' : '' ?>"><?= num($alertTotal) ?></div>
      <div class="k-foot"><?= num($c['out_stock'] ?? 0) ?> already sold out</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <span><i class="bi bi-arrow-left-right"></i> Stock Coming In and Going Out</span>
        <a class="btn btn-sm btn-outline-secondary ms-auto no-print"
           href="<?= url('inventory') ?>">Open inventory</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>When</th><th>Item</th><th class="text-center">Movement</th>
            <th class="text-end">Qty</th><th class="text-end">On hand after</th></tr></thead>
          <tbody>
          <?php if (!$movements): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">
              Nothing has moved yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($movements as $m):
            $kind = [
              'in'     => ['Received', 'success', 'box-arrow-in-down'],
              'out'    => ['Sold',     'secondary', 'box-arrow-right'],
              'adjust' => ['Corrected','info', 'sliders'],
            ][$m['type']] ?? ['Movement', 'secondary', 'dot']; ?>
            <tr>
              <td class="text-muted-sm" style="white-space:nowrap">
                <?= date('M j', strtotime($m['created_at'])) ?><br>
                <?= date('g:i A', strtotime($m['created_at'])) ?>
              </td>
              <td>
                <a class="fw-semibold text-decoration-none"
                   href="<?= url('inventory.view', ['id' => $m['product_id']]) ?>">
                  <?= e($m['product_name']) ?></a>
                <div class="mono text-muted-sm"><?= e($m['sku']) ?>
                  <?= $m['reference'] ? ' &middot; ' . e($m['reference']) : '' ?></div>
              </td>
              <td class="text-center">
                <span class="badge badge-soft-<?= $kind[1] ?>">
                  <i class="bi bi-<?= $kind[2] ?>"></i> <?= e($kind[0]) ?></span>
              </td>
              <td class="text-end fw-semibold"><?= num($m['quantity']) ?></td>
              <td class="text-end text-muted-sm"><?= num($m['balance']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <span><i class="bi bi-exclamation-triangle"></i> Running Low</span>
        <a class="btn btn-sm btn-outline-secondary ms-auto no-print"
           href="<?= url('alerts') ?>">All alerts</a>
      </div>
      <ul class="list-group list-group-flush">
        <?php if (!$alerts): ?>
          <li class="list-group-item text-center text-muted py-4">
            Everything is comfortably stocked.</li>
        <?php endif; ?>
        <?php foreach ($alerts as $a): ?>
          <li class="list-group-item d-flex align-items-center gap-2">
            <div class="flex-grow-1">
              <a class="fw-semibold text-decoration-none"
                 href="<?= url('inventory.view', ['id' => $a['id']]) ?>"><?= e($a['name']) ?></a>
              <div class="text-muted-sm"><?= e($a['category_name'] ?? 'Uncategorised') ?></div>
            </div>
            <span class="badge badge-soft-<?= (int) $a['quantity'] <= 0 ? 'danger' : 'warning' ?>">
              <?= num($a['quantity']) ?> left</span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <span><i class="bi bi-tag"></i> My Price Requests</span>
        <a class="btn btn-sm btn-outline-secondary ms-auto no-print"
           href="<?= url('approvals') ?>">See all</a>
      </div>
      <ul class="list-group list-group-flush">
        <?php if (!$myRequests): ?>
          <li class="list-group-item text-muted-sm py-3">
            <?php if (Auth::canSetPrice()): ?>
              You have not asked to lower any price. Adding an item, pricing it
              and putting a price up you can do yourself.
            <?php else: ?>
              You have not asked to change any price. Adding an item and setting
              its opening price you can do yourself.
            <?php endif; ?>
          </li>
        <?php endif; ?>
        <?php foreach ($myRequests as $r):
          $tone = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$r['status']]; ?>
          <li class="list-group-item d-flex align-items-center gap-2">
            <div class="flex-grow-1">
              <div class="fw-semibold"><?= e($r['product_name']) ?></div>
              <div class="text-muted-sm"><?= peso($r['old_price']) ?>
                &rarr; <?= peso($r['new_price']) ?></div>
            </div>
            <span class="badge badge-soft-<?= $tone ?>"><?= ucfirst(e($r['status'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="fw-semibold mb-1"><i class="bi bi-shield-lock"></i> Discounts at the till</div>
        <div class="text-muted-sm">
          A discount is money off the owner's goods, so it is not yours to give.
          Request the discount percentage at the till. The administrator or a Store
          Manager approves it &mdash; their name goes on the sale.
        </div>
      </div>
    </div>
  </div>
</div>
