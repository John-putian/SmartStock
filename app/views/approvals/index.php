<?php
/**
 * FR-01 / FR-06 - price reduction approvals.
 * Administrators and managers rule on requests here; work staff watch theirs.
 */
$pills = [
  ''         => ['All requests',  'collection'],
  'pending'  => ['Waiting',       'hourglass-split'],
  'approved' => ['Approved',      'check2-circle'],
  'rejected' => ['Rejected',      'x-circle'],
];
?>

<div class="d-flex flex-wrap gap-2 mb-3 no-print">
  <?php foreach ($pills as $key => [$label, $icon]): ?>
    <a class="btn btn-sm <?= $status === $key ? 'btn-primary' : 'btn-outline-secondary' ?>"
       href="<?= url('approvals', array_filter(['status' => $key])) ?>">
      <i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="alert alert-info d-flex gap-3 align-items-start no-print">
  <i class="bi bi-shield-lock fs-4"></i>
  <div>
    <?php if ($canDecide && !$canDecideDiscount): ?>
      <strong>Price changes can be decided here.</strong> Discounts are approved by the administrator or a Store Manager. Discount requests are shown for oversight.
    <?php elseif ($canDecide): ?>
      <strong>Nothing here has taken effect yet.</strong>
      Two things wait for you. A <em>discount request</em> is a percentage off
      one sale that is standing still at the till until you answer &mdash; deal
      with those first. A <em>price request</em> is a change to a price already
      on the shelf, which can wait. Approving a price writes it straight onto
      the item; approving a discount lets that till take the percentage off
      that one sale, once. Either way the decision is stamped with your name in
      the audit trail.
    <?php elseif (Auth::isTill()): ?>
      <strong>What you have asked for.</strong>
      A discount is money off the owner's goods, so it is not yours to give
      &mdash; you ask, and only the Store Manager decides the discount. The sale waits
      at full price until they do. The same goes for changing a price that is
      already listed.
    <?php elseif (Auth::canSetPrice()): ?>
      <strong>Prices you asked to lower.</strong>
      You can list an item, price it, and put a price up yourself. Lowering one
      needs the administrator or the manager to agree first, so the item keeps
      its current price until they decide.
    <?php else: ?>
      <strong>Price changes you asked for.</strong>
      You can add an item and set its opening price. Once it is listed, changing
      that price &mdash; up or down &mdash; needs the administrator or the
      manager to agree, so the item keeps its current price until they decide.
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-4">
    <div class="kpi">
      <div class="k-label"><i class="bi bi-hourglass-split"></i> Waiting on a decision</div>
      <div class="k-value text-warning"><?= num($counts['pending']) ?></div>
      <div class="k-foot"><?= $canDecide ? 'Across all staff' : 'Your requests' ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="kpi">
      <div class="k-label"><i class="bi bi-check2-circle"></i> Approved</div>
      <div class="k-value text-success"><?= num($counts['approved']) ?></div>
      <div class="k-foot">Price change applied</div>
    </div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="kpi">
      <div class="k-label"><i class="bi bi-x-circle"></i> Rejected</div>
      <div class="k-value text-danger"><?= num($counts['rejected']) ?></div>
      <div class="k-foot">Listed price kept</div>
    </div>
  </div>
</div>


<?php /* FR-04b - discounts waiting at the till. Put first on purpose: a sale
         is standing still while this is unanswered, where a price request is
         only a shelf ticket that can wait an hour. */ ?>
<?php if ($discounts): ?>
<div class="card mb-3">
  <div class="card-header d-flex align-items-center">
    <span><i class="bi bi-percent"></i> Discount Requests</span>
    <?php $waiting = 0; foreach ($discounts as $d) { if ($d['status'] === 'pending') $waiting++; } ?>
    <?php if ($waiting): ?>
      <span class="badge bg-danger rounded-pill ms-2"><?= num($waiting) ?> waiting</span>
      <span class="text-muted-sm ms-2">a sale is held at the till for each of these</span>
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr>
        <th>Asked by</th><th class="text-end">Discount</th>
        <th class="text-end">Cart total</th><th class="text-end">Comes off</th>
        <th class="text-end">Customer pays</th>
        <th>Reason</th><th>Status</th><th class="no-print"></th>
      </tr></thead>
      <tbody>
      <?php foreach ($discounts as $d):
        $pct   = (float) $d['percent'];
        $cart  = (float) $d['cart_total'];
        $off   = round($cart * $pct / 100, 2);
        $tone  = ['pending'=>'warning', 'approved'=>'success', 'rejected'=>'secondary', 'cancelled'=>'secondary', 'expired'=>'secondary', 'used'=>'info'][$d['status']] ?? 'secondary'; ?>
        <tr class="<?= $d['status'] === 'pending' ? 'table-warning' : '' ?>">
          <td>
            <div class="fw-semibold"><?= e((string) $d['asked_by_name']) ?></div>
            <span class="badge badge-soft-<?= role_class((string) $d['asked_by_role']) ?>">
              <?= e(role_label((string) $d['asked_by_role'])) ?></span>
            <div class="text-muted-sm"><?= date('M j, g:i A', strtotime($d['created_at'])) ?></div>
          </td>
          <td class="text-end">
            <span class="fs-5 fw-bold <?= $pct >= 100 ? 'text-danger' : '' ?>">
              <?= e(DiscountRequest::pct($pct)) ?></span>
            <?php if ($pct >= 100): ?>
              <div class="badge badge-soft-danger">free of charge</div>
            <?php endif; ?>
          </td>
          <td class="text-end mono"><?= peso($cart) ?></td>
          <td class="text-end mono text-danger">&minus;<?= peso($off) ?></td>
          <td class="text-end mono fw-bold"><?= peso(round($cart - $off, 2)) ?></td>
          <td class="text-muted-sm" style="max-width:16rem">
            <?= $d['reason'] ? e($d['reason']) : '<span class="text-muted">&mdash;</span>' ?>
            <?php if (!empty($d['decision_note'])): ?>
              <div class="fst-italic">&ldquo;<?= e($d['decision_note']) ?>&rdquo;</div>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge badge-soft-<?= $tone ?>"><?= e(ucfirst($d['status'])) ?></span>
            <?php if ($d['decided_by_name']): ?>
              <div class="text-muted-sm">by <?= e((string) $d['decided_by_name']) ?></div>
            <?php endif; ?>
            <?php if (!empty($d['invoice_no'])): ?>
              <div class="text-muted-sm">used on <?= e((string) $d['invoice_no']) ?></div>
            <?php elseif ($d['status'] === 'approved'): ?>
              <div class="text-muted-sm">not used yet</div>
            <?php endif; ?>
          </td>
          <td class="text-end no-print">
            <?php if ($canDecideDiscount && $d['status'] === 'pending'): ?>
              <form method="post" action="<?= url('approvals.discount') ?>"
                    class="d-flex gap-1 justify-content-end">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <input name="note" class="form-control form-control-sm d-none d-lg-block"
                       style="max-width:11rem" maxlength="250" placeholder="Note (optional)">
                <button name="decision" value="approve" class="btn btn-sm btn-success">
                  <i class="bi bi-check-lg"></i> Approve</button>
                <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">
                  <i class="bi bi-x-lg"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><i class="bi bi-tag"></i>
    <?= $canDecide ? 'Price Reduction Requests' : 'My Price Reduction Requests' ?>
    <span class="text-muted-sm">&middot; <?= num(count($requests)) ?> record(s)</span>
  </div>

  <?php if (!$requests): ?>
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-inbox fs-1 d-block mb-2"></i>
      Nothing to show. Prices are all where they should be.
    </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Item</th>
          <th>Asked by</th>
          <th class="text-end">Listed</th>
          <th class="text-end">Asked for</th>
          <th class="text-end">Change</th>
          <th>Reason given</th>
          <th>Status</th>
          <?php if ($canDecide): ?><th class="text-end no-print">Decision</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($requests as $r):
        $old  = (float) $r['old_price'];
        $new  = (float) $r['new_price'];
        $drop = $old - $new;
        $pct  = $old > 0 ? $drop / $old * 100 : 0;
        $tone = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$r['status']];
        $img  = product_image($r['image_path'] ?? null, true);
      ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="inv-thumb">
                <?php if ($img): ?>
                  <img src="<?= e($img) ?>" alt="<?= e($r['product_name']) ?>">
                <?php else: ?>
                  <i class="bi bi-<?= category_icon($r['category_name'] ?? '') ?>"></i>
                <?php endif; ?>
              </div>
              <div>
                <a class="fw-semibold text-decoration-none"
                   href="<?= url('inventory.view', ['id' => $r['product_id']]) ?>">
                  <?= e($r['product_name']) ?></a>
                <div class="mono text-muted-sm"><?= e($r['sku']) ?>
                  &middot; <?= e($r['category_name'] ?? 'Uncategorised') ?></div>
              </div>
            </div>
          </td>
          <td>
            <div class="fw-semibold"><?= e($r['asked_by_name'] ?? 'Removed account') ?></div>
            <div class="text-muted-sm"><?= e(role_label((string) ($r['asked_by_role'] ?? 'staff'))) ?>
              &middot; <?= date('M j, g:i A', strtotime($r['created_at'])) ?></div>
          </td>
          <td class="text-end"><?= peso($old) ?></td>
          <td class="text-end fw-bold"><?= peso($new) ?></td>
          <td class="text-end">
            <?php /* A cashier's request can go either way - they may not move a
                     listed price at all. Staff can only ever be asking to
                     lower one. */ ?>
            <span class="badge badge-soft-<?= $drop > 0 ? 'danger' : 'success' ?>">
              <?= $drop > 0 ? '&minus;' : '+' ?><?= peso(abs($drop)) ?></span>
            <div class="text-muted-sm">
              <?= number_format(abs($pct), 1) ?>% <?= $drop > 0 ? 'off' : 'up' ?></div>
          </td>
          <td style="max-width:260px">
            <div class="text-muted-sm"><?= e($r['reason'] ?: 'No reason given.') ?></div>
            <?php if ($r['decision_note']): ?>
              <div class="small mt-1"><i class="bi bi-reply-fill text-muted"></i>
                <em><?= e($r['decision_note']) ?></em></div>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge badge-soft-<?= $tone ?>"><?= ucfirst(e($r['status'])) ?></span>
            <?php if ($r['status'] !== 'pending'): ?>
              <div class="text-muted-sm mt-1">
                by <?= e($r['decided_by_name'] ?? 'system') ?><br>
                <?= $r['decided_at'] ? date('M j, Y', strtotime($r['decided_at'])) : '' ?>
              </div>
            <?php endif; ?>
          </td>
          <?php if ($canDecide): ?>
            <td class="text-end no-print">
              <?php if ($r['status'] === 'pending'): ?>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-success" title="Approve this reduction"
                          onclick='decide(<?= (int) $r['id'] ?>, "approve", <?= js_json($r['product_name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= js_json(peso($old, false)) ?>, <?= js_json(peso($new, false)) ?>)'>
                    <i class="bi bi-check-lg"></i></button>
                  <button class="btn btn-outline-danger" title="Reject this reduction"
                          onclick='decide(<?= (int) $r['id'] ?>, "reject", <?= js_json($r['product_name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= js_json(peso($old, false)) ?>, <?= js_json(peso($new, false)) ?>)'>
                    <i class="bi bi-x-lg"></i></button>
                </div>
              <?php else: ?>
                <span class="text-muted-sm">Closed</span>
              <?php endif; ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php if ($canDecide): ?>
<!-- Decision dialog: one form, filled in by the buttons above. -->
<div class="modal fade" id="decideModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= url('approvals.decide') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" id="dcId">
      <input type="hidden" name="decision" id="dcDecision">
      <input type="hidden" name="status" value="<?= e($status) ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="dcTitle">Decide</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2" id="dcBody"></p>
        <label class="form-label fw-semibold">Note for the record</label>
        <textarea name="note" id="dcNote" class="form-control" rows="3"
                  placeholder="Why you allowed it, or why not. Kept with the request."></textarea>
        <div class="form-text">Your name, the decision and this note all go into the audit trail.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn" id="dcConfirm"></button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($canDecide): $inlineScript = <<<'JS'
function decide(id, what, name, oldPrice, newPrice) {
  var approve = what === 'approve';
  document.getElementById('dcId').value = id;
  document.getElementById('dcDecision').value = what;
  document.getElementById('dcNote').value = '';
  document.getElementById('dcTitle').textContent =
      approve ? 'Approve this price reduction?' : 'Reject this price reduction?';
  document.getElementById('dcBody').innerHTML = approve
      ? '<strong>' + name + '</strong> will be re-listed at ₱' + newPrice +
        ', down from ₱' + oldPrice + '. The new price takes effect immediately.'
      : '<strong>' + name + '</strong> will keep its price of ₱' + oldPrice +
        '. The request to lower it to ₱' + newPrice + ' will be closed.';
  var btn = document.getElementById('dcConfirm');
  btn.className = 'btn ' + (approve ? 'btn-success' : 'btn-danger');
  btn.innerHTML = approve
      ? '<i class="bi bi-check-lg"></i> Approve the new price'
      : '<i class="bi bi-x-lg"></i> Reject and keep the price';
  new bootstrap.Modal(document.getElementById('decideModal')).show();
}
JS; endif; ?>
