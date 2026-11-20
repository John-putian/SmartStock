<?php
[$label, $tone] = stock_status((int) $p['quantity'], (int) $p['reorder_level']);
$margin = (float) $p['selling_price'] - (float) $p['cost_price'];
$marginPct = (float) $p['selling_price'] > 0 ? $margin / (float) $p['selling_price'] * 100 : 0;
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3 no-print">
  <a href="<?= url('inventory') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i> Back</a>
  <div class="ms-auto d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" target="_blank"
       href="<?= url('inventory.units', ['id' => $p['id'], 'only' => 'stock']) ?>">
      <i class="bi bi-qr-code"></i> Unit Labels</a>
    <?php if (Auth::canEditItems()): ?>
      <a class="btn btn-sm btn-outline-primary" href="<?= url('inventory.edit', ['id' => $p['id']]) ?>">
        <i class="bi bi-pencil"></i> Edit</a>
    <?php endif; ?>
    <?php if (Auth::canEditItems()): ?>
      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#stockModal">
        <i class="bi bi-box-arrow-in-down"></i> Receive / Adjust Stock</button>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($priceRequest)): ?>
  <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center no-print">
    <i class="bi bi-hourglass-split fs-5"></i>
    <div class="flex-grow-1">
      <strong>A price reduction is waiting for a decision.</strong>
      Someone asked to bring this item down from <?= peso($priceRequest['old_price']) ?>
      to <?= peso($priceRequest['new_price']) ?>. The listed price below has not
      changed and will not change until it is approved.
    </div>
    <a class="btn btn-sm btn-warning" href="<?= url('approvals', ['status' => 'pending']) ?>">
      <?= Auth::canApprove() ? 'Decide now' : 'View my request' ?></a>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex flex-wrap align-items-start gap-3">
          <?php $img = product_image($p['image_path'] ?? null); ?>
          <div class="item-hero">
            <?php if ($img): ?>
              <a href="<?= e($img) ?>" target="_blank" title="Open full size">
                <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>"></a>
            <?php else: ?>
              <i class="bi bi-<?= category_icon($p['category_name'] ?? '') ?>"></i>
            <?php endif; ?>
          </div>
          <div class="flex-grow-1" style="min-width:280px">
            <div class="mono text-muted-sm"><?= e($p['sku']) ?><?= $p['serial_no'] ? ' &middot; SN ' . e($p['serial_no']) : '' ?></div>
            <h2 class="h4 fw-bold mb-1"><?= e($p['name']) ?></h2>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <span class="badge badge-soft-primary"><?= e($p['category_name'] ?? 'Uncategorised') ?></span>
              <span class="badge badge-soft-<?= condition_class($p['condition_grade']) ?>"><?= e($p['condition_grade']) ?></span>
              <span class="badge badge-soft-<?= $tone ?>"><?= e($label) ?></span>
              <?php if ($p['brand']): ?><span class="badge badge-soft-secondary"><?= e($p['brand']) ?></span><?php endif; ?>
            </div>
            <p class="text-muted mb-0"><?= nl2br(e($p['description'] ?: 'No description recorded.')) ?></p>
            <?php if (!$img): ?>
              <div class="alert alert-light border mt-3 mb-0 py-2 small no-print">
                <i class="bi bi-camera"></i> No photograph on file. Because every second-hand unit
                differs, add a photo of this actual piece from
                <a href="<?= url('inventory.edit', ['id' => $p['id']]) ?>">Edit</a>.
              </div>
            <?php endif; ?>
          </div>
          <div class="text-end">
            <div class="text-muted-sm">Selling price</div>
            <div class="h3 fw-bold text-primary mb-0"><?= peso($p['selling_price']) ?></div>
            <div class="text-muted-sm">Cost <?= peso($p['cost_price']) ?> &middot;
              margin <?= number_format($marginPct, 1) ?>%</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <span><i class="bi bi-upc-scan"></i> Unit Register</span>
        <span class="text-muted-sm">&middot; <?= num(count($units)) ?> piece(s) ever registered</span>
        <a class="btn btn-sm btn-outline-secondary ms-auto no-print" target="_blank"
           href="<?= url('inventory.units', ['id' => $p['id'], 'only' => 'stock']) ?>">
          <i class="bi bi-printer"></i> Print labels for stock</a>
      </div>
      <div class="card-body py-2 border-bottom text-muted-sm">
        Every physical piece carries its own barcode, condition grade and remarks, so a
        one-off table has a single label and five matching chairs have five separate ones.
      </div>
      <div class="table-responsive" style="max-height:340px;overflow:auto">
        <table class="table table-sm table-hover mb-0">
          <thead class="position-sticky top-0"><tr>
            <th>Barcode</th><th>Condition</th><th>Remarks</th>
            <th>Status</th><th>Received</th><th class="no-print"></th></tr></thead>
          <tbody>
          <?php if (!$units): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">
              No pieces registered yet &mdash; receive stock to create labelled units.</td></tr>
          <?php endif; ?>
          <?php foreach ($units as $u):
                $st = ['in_stock' => ['On floor', 'success'], 'sold' => ['Sold', 'secondary'],
                       'reserved' => ['Reserved', 'info'], 'retired' => ['Retired', 'danger']][$u['status']]; ?>
            <tr>
              <td class="mono fw-semibold"><?= e($u['unit_code']) ?></td>
              <td><span class="badge badge-soft-<?= condition_class($u['condition_grade'] ?: $p['condition_grade']) ?>">
                  <?= e($u['condition_grade'] ?: $p['condition_grade']) ?></span></td>
              <td class="text-muted-sm"><?= e($u['notes'] ?: '—') ?></td>
              <td><span class="badge badge-soft-<?= $st[1] ?>"><?= e($st[0]) ?></span>
                <?php if ($u['invoice_no']): ?>
                  <div class="text-muted-sm mono"><?= e($u['invoice_no']) ?></div>
                <?php endif; ?></td>
              <td class="text-muted-sm"><?= date('M j, Y', strtotime($u['received_at'])) ?></td>
              <td class="text-end no-print">
                <?php if ($u['status'] === 'in_stock' && Auth::canEditItems()): ?>
                  <button class="btn btn-sm btn-outline-secondary py-0"
                          onclick='editUnit(<?= js_json($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                          title="Edit this piece"><i class="bi bi-pencil"></i></button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><i class="bi bi-clock-history"></i> Stock Movement Ledger</div>
      <div class="table-responsive" style="max-height:420px;overflow:auto">
        <table class="table table-sm table-hover mb-0">
          <thead class="position-sticky top-0">
            <tr><th>Date &amp; time</th><th>Type</th><th class="text-center">Qty</th>
                <th class="text-center">Balance</th><th>Reference</th><th>Recorded by</th></tr>
          </thead>
          <tbody>
          <?php if (!$movements): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No stock movements recorded yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($movements as $m):
                $tone2 = $m['type'] === 'in' ? 'success' : ($m['type'] === 'out' ? 'danger' : 'warning');
                $sign  = $m['type'] === 'in' ? '+' : ($m['type'] === 'out' ? '−' : '±'); ?>
            <tr>
              <td class="text-nowrap"><?= date('M j, Y g:i A', strtotime($m['created_at'])) ?></td>
              <td><span class="badge badge-soft-<?= $tone2 ?>"><?= strtoupper(e($m['type'])) ?></span></td>
              <td class="text-center fw-semibold text-<?= $tone2 ?>"><?= $sign . num($m['quantity']) ?></td>
              <td class="text-center"><?= num($m['balance']) ?></td>
              <td class="mono text-muted-sm"><?= e($m['reference']) ?>
                <div class="text-muted-sm"><?= e($m['notes']) ?></div></td>
              <td class="text-muted-sm"><?= e($m['full_name'] ?? 'System') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-boxes"></i> Stock Position</div>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-end mb-2">
          <div><div class="text-muted-sm">On hand</div>
            <div class="display-6 fw-bold"><?= num($p['quantity']) ?></div></div>
          <div class="text-end"><div class="text-muted-sm">Reorder level</div>
            <div class="h4 fw-bold mb-0"><?= num($p['reorder_level']) ?></div></div>
        </div>
        <?php $pct = min(100, (int) $p['reorder_level'] > 0
                    ? (int) $p['quantity'] / max(1, (int) $p['reorder_level'] * 3) * 100 : 100); ?>
        <div class="progress progress-thin mb-3">
          <div class="progress-bar bg-<?= $tone ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <table class="table table-sm mb-0">
          <tr><td class="text-muted">Stock value (at cost)</td>
              <td class="text-end fw-semibold"><?= peso((float) $p['quantity'] * (float) $p['cost_price']) ?></td></tr>
          <tr><td class="text-muted">Retail value</td>
              <td class="text-end fw-semibold"><?= peso((float) $p['quantity'] * (float) $p['selling_price']) ?></td></tr>
          <tr><td class="text-muted">Units sold (all time)</td>
              <td class="text-end fw-semibold"><?= num($sold['units']) ?></td></tr>
          <tr><td class="text-muted">Revenue generated</td>
              <td class="text-end fw-semibold"><?= peso($sold['revenue']) ?></td></tr>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Receive / adjust stock -->
<div class="modal fade" id="stockModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= url('inventory.stockin') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $p['id'] ?>">
      <div class="modal-header"><h5 class="modal-title">Receive or Adjust Stock</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="text-muted-sm"><?= e($p['name']) ?> &middot; currently <strong><?= num($p['quantity']) ?></strong> unit(s) on hand.</p>
        <div class="mb-3">
          <label class="form-label fw-semibold">Action</label>
          <select name="mode" class="form-select">
            <option value="in">Receive stock (add to current quantity)</option>
            <option value="adjust">Physical count correction (set exact quantity)</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Quantity</label>
          <input name="qty" type="number" min="0" class="form-control" value="1" required>
        </div>
        <div class="mb-0">
          <label class="form-label fw-semibold">Notes</label>
          <input name="notes" class="form-control" placeholder="e.g. Container delivery DR-2609">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Record Movement</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit one physical piece -->
<div class="modal fade" id="unitModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= url('inventory.unit') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="unit_id" id="unitId">
      <div class="modal-header"><h5 class="modal-title">Edit Piece <span id="unitCode" class="mono"></span></h5>
        <button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
      <div class="modal-body">
        <p class="text-muted-sm">
          Each second-hand piece is graded on its own merits, so this condition and these
          remarks apply to this single unit &mdash; not to every piece of the item.
        </p>
        <div class="mb-3">
          <label class="form-label fw-semibold">Condition of this piece</label>
          <select name="condition_grade" id="unitCond" class="form-select">
            <?php foreach (['Class A', 'Class B', 'Class C', 'For Parts'] as $c): ?>
              <option value="<?= e($c) ?>"><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-0">
          <label class="form-label fw-semibold">Remarks</label>
          <input name="notes" id="unitNotes" class="form-control"
                 placeholder="e.g. Small dent on the left leg">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">Cancel</button>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Save Piece</button>
      </div>
    </form>
  </div>
</div>

<?php
$inlineScript = <<<JS
function editUnit(u) {
  document.getElementById('unitId').value = u.id;
  document.getElementById('unitCode').textContent = u.unit_code;
  document.getElementById('unitCond').value = u.condition_grade || 'Class B';
  document.getElementById('unitNotes').value = u.notes || '';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('unitModal')).show();
}
window.editUnit = editUnit;

JS;
?>
