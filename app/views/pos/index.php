<?php /** FR-04 - point-of-sale terminal. */ ?>

<div class="d-lg-none mb-3"><a class="btn btn-outline-primary w-100" href="#currentSale">View current sale (<?= count($cart) ?> item types) &amp; checkout</a></div>
<div class="pos-grid" id="itemPicker">

  <!-- ------------------------------------------------ product picker -->
  <div>
    <div class="card mb-3 no-print">
      <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-center">
          <input type="hidden" name="page" value="pos">
          <div class="col-12 col-md-5">
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input aria-label="Search products" name="q" value="<?= e($q) ?>" class="form-control"
                     placeholder="Search item name or brand" data-live-search>
            </div>
          </div>
          <div class="col-7 col-md-4">
            <select aria-label="Filter by category" name="category" class="form-select" data-autosubmit>
              <option value="">All categories</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (string) $category === (string) $c['id'] ? 'selected' : '' ?>>
                  <?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>
        <form method="post" action="<?= url('pos.scan') ?>" class="mt-2" id="scanForm">
          <?= csrf_field() ?>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
            <input aria-label="Scan unit barcode or item SKU" id="scanBox" name="code" class="form-control" autocomplete="off" autofocus
                   placeholder="Scan a unit barcode (e.g. AA-FUR-0007-03) and press Enter"
                   title="FR-03: scan the QR label on the actual piece to add that exact unit">
            <button class="btn btn-outline-secondary" type="submit">Add piece</button>
          </div>
          <div class="form-text">
            Scanning adds the <strong>exact piece</strong> on the label. Clicking a card below
            takes the oldest one of that item off the floor.
          </div>
          <details class="form-text mt-1">
            <summary class="text-decoration-underline" style="cursor:pointer">How the scanner works here</summary>
            <div class="mt-1">
              <p class="mb-1">This box reads <strong>typed characters</strong>, not pictures. A USB or
                Bluetooth barcode scanner behaves like a keyboard: point it at the QR label on the
                piece, and it types the code into this box and presses Enter for you.</p>
              <p class="mb-1"><strong>This screen never uses the camera.</strong> There is no
                camera-based QR reading anywhere in Smart Stock. On a phone or on a computer with no
                scanner attached, either type the code from the label by hand and press Enter, or
                simply tap the item&rsquo;s card below.</p>
              <p class="mb-0">Taking a <em>photo</em> of a product is a different job entirely &mdash;
                that happens on the Add item screen, and it records what the item looks like. It does
                not read a code.</p>
            </div>
          </details>
        </form>
      </div>
    </div>

    <div class="row g-2">
      <?php if (!$products): ?>
        <div class="col-12 text-center text-muted py-5">
          <i class="bi bi-inbox fs-3 d-block mb-2"></i>No items match your search.</div>
      <?php endif; ?>

      <?php foreach ($products as $p):
            $out = (int) $p['quantity'] < 1;
            [$label, $tone] = stock_status((int) $p['quantity'], (int) $p['reorder_level']); ?>
        <div class="col-6 col-xl-4">
          <form method="post" action="<?= url('pos.add', ['id' => $p['id'], 'q' => $q, 'category' => $category]) ?>">
            <?= csrf_field() ?>
          <button type="submit" class="pos-item d-block text-dark" <?= $out ? 'disabled' : '' ?>>
            <?php $img = product_image($p['image_path'] ?? null, true); ?>
            <div class="pos-photo">
              <?php if ($img): ?>
                <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
              <?php else: ?>
                <i class="bi bi-<?= category_icon($p['category_name'] ?? '') ?> ph"></i>
              <?php endif; ?>
              <span class="badge badge-soft-<?= $tone ?> tag-stock"><?= e($label) ?></span>
              <span class="badge tag-class cls-<?= strtolower(str_replace(' ', '-', $p['condition_grade'])) ?>">
                <?= e($p['condition_grade']) ?></span>
            </div>
            <div class="nm mt-2"><?= e($p['name']) ?></div>
            <div class="text-muted d-flex justify-content-between" style="font-size:.72rem">
              <span><?= e($p['category_name'] ?? '') ?></span>
              <span class="mono fw-semibold"><?= e($p['sku']) ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <span class="pr"><?= peso($p['selling_price']) ?></span>
              <span class="text-muted" style="font-size:.75rem"><?= num($p['quantity']) ?> left</span>
            </div>
          </button></form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ----------------------------------------------------- cart panel -->
  <div class="pos-cart" id="currentSale">
    <a class="btn btn-outline-secondary w-100 mb-2 d-lg-none" href="#itemPicker">Back to item picker</a>
    <div class="card">
      <div class="card-header d-flex align-items-center">
        <i class="bi bi-cart3 me-2"></i> Current Sale
        <span class="ms-auto mono text-muted-sm"><?= e($invoice) ?></span>
      </div>

      <div class="card-body pt-2 pb-0" style="max-height:300px;overflow:auto">
        <?php if (!$cart): ?>
          <div class="text-center text-muted py-4">
            <i class="bi bi-cart-x fs-3 d-block mb-2"></i>
            <div class="small">Click an item on the left to add it to the sale.</div>
          </div>
        <?php else: ?>
          <form method="post" action="<?= url('pos.update') ?>" id="qtyForm">
            <?= csrf_field() ?>
          </form>
            <?php foreach ($cart as $row): $p = $row['p']; ?>
              <div class="cart-line">
                <div class="d-flex gap-2 align-items-center">
                  <div class="flex-grow-1">
                    <div class="fw-semibold" style="font-size:.85rem"><?= e($p['name']) ?></div>
                    <div class="text-muted-sm"><?= peso($p['selling_price']) ?> each
                      &middot; <?= num($p['quantity']) ?> on the floor</div>
                  </div>
                  <input type="number" min="1" max="<?= (int) $p['quantity'] ?>"
                         aria-label="Quantity for <?= e($p['name']) ?>" form="qtyForm" name="qty[<?= $p['id'] ?>]" value="<?= (int) $row['qty'] ?>"
                         class="form-control form-control-sm text-center" style="width:60px"
                         onchange="document.getElementById('qtyForm').submit()">
                  <div class="text-end" style="width:84px">
                    <div class="fw-bold" style="font-size:.87rem"><?= peso($row['line']) ?></div>
                    <form method="post" action="<?= url('pos.remove', ['id' => $p['id']]) ?>" class="d-inline">
<?= csrf_field() ?>
<button type="submit" class="text-muted small" >
                      <i class="bi bi-trash"></i> remove</button></form>
                  </div>
                </div>
                <div class="unit-chips">
                  <?php foreach ($row['units'] as $u): ?>
                    <span class="unit-chip" title="<?= e($u['condition_grade'] ?: $p['condition_grade']) ?><?= $u['notes'] ? ' — ' . e($u['notes']) : '' ?>">
                      <?= e($u['unit_code']) ?>
                      <form method="post" action="<?= url('pos.remove', ['unit' => $u['id']]) ?>" class="d-inline">
<?= csrf_field() ?>
<button type="submit"  title="Put this piece back">&times;</button></form>
                    </span>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <?php /* Its own form, because a form cannot be nested inside another.
               The fields live in the card below and point here by id. */ ?>
      <form method="post" action="<?= url('pos.discount') ?>" id="discForm" class="d-none">
        <?= csrf_field() ?>
      </form>

      <form method="post" action="<?= url('pos.checkout') ?>" class="card-body border-top" id="payForm">
        <?= csrf_field() ?>
        <div class="totals-row">
          <span class="text-muted">Subtotal (<?= count($cart) ?> line item(s))</span>
          <span class="fw-semibold" id="subLbl"><?= peso($subtotal) ?></span>
        </div>

        <div class="row g-2 mt-1">
          <div class="col-12">
            <label class="form-label mb-1 small fw-semibold">Payment</label>
            <select name="payment_method" class="form-select form-select-sm">
              <option>Cash</option><option>Bank Transfer</option>
              <!-- Installment was offered here but checkout has always demanded the
                   full amount, so choosing it changed nothing and misled the cashier.
                   Restore it only together with a real balance and payment-history
                   workflow (outstanding amount, schedule, part payments). -->
            </select>
          </div>
        </div>

        <?php /*
          FR-04b : a discount is money off the owner's goods, so it is not the
          cashier's to give. The till states a percentage and a reason; the
          office decides. The cart stays at full price until it does.
        */ ?>

        <?php if ($request && $request['status'] === 'pending'): ?>
          <div class="border rounded p-2 mt-2 bg-warning-subtle" id="discPending">
            <div class="small fw-semibold mb-1">
              <i class="bi bi-hourglass-split"></i>
              <?= e(DiscountRequest::pct((float) $request['percent'])) ?> discount &mdash; waiting on a decision
            </div>
            <div class="text-muted-sm mb-2">
              A Store Manager has been notified. This sale cannot be
              completed until they rule on it.
              <?php if (!empty($request['reason'])): ?>
                <br><span class="fst-italic">&ldquo;<?= e($request['reason']) ?>&rdquo;</span>
              <?php endif; ?>
            </div>
            <div class="d-flex gap-2 align-items-center">
              <a href="<?= url('pos') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-clockwise"></i> Check again</a>
              <button class="btn btn-sm btn-link text-danger p-0 ms-auto"
                      formaction="<?= url('pos.discount.cancel') ?>" formnovalidate
                      name="id" value="<?= (int) $request['id'] ?>">Withdraw</button>
            </div>
          </div>

        <?php elseif ($request && $request['status'] === 'approved'): ?>
          <?php if (!empty($request['stale'])): ?>
            <div class="alert alert-warning"><?= e($request['stale_reason'] ?? 'This sale changed after approval. Cancel the old discount and request it again. No discount is applied.') ?></div>
          <?php endif; ?>
          <div class="border rounded p-2 mt-2 bg-success-subtle">
            <div class="small fw-semibold mb-1">
              <i class="bi bi-patch-check-fill text-success"></i>
              <?= e(DiscountRequest::pct((float) $request['percent'])) ?> approved
            </div>
            <div class="text-muted-sm">
              Authorised by <?= e((string) ($request['decided_by_name'] ?? 'the office')) ?>.
              You apply it by completing the sale below. It comes off this sale
              only, once, and it prints on the receipt.
            </div>
            <button class="btn btn-sm btn-link text-danger p-0 mt-1"
                    formaction="<?= url('pos.discount.cancel') ?>" formnovalidate
                    name="id" value="<?= (int) $request['id'] ?>">Do not use it</button>
          </div>

          <div class="totals-row">
            <span class="text-muted">
              Discount <?= e(DiscountRequest::pct((float) $request['percent'])) ?>
              of <?= peso($subtotal) ?></span>
            <span class="fw-semibold text-danger">&minus;&nbsp;<?= peso($discount) ?></span>
          </div>
          <div class="totals-row">
            <span class="text-success fw-semibold">
              <i class="bi bi-piggy-bank"></i> Customer saves</span>
            <span class="text-success fw-semibold"><?= peso($discount) ?></span>
          </div>

        <?php elseif ($cart): ?>
          <div class="border rounded p-2 mt-2 bg-light">
            <div class="small fw-semibold mb-1">
              <i class="bi bi-percent"></i> Ask for a discount
            </div>
            <div class="text-muted-sm mb-2">
              You apply the discount to this sale &mdash; the manager only
              authorises the amount. State how much and why.
            </div>
            <div class="row g-2">
              <div class="col-4">
                <div class="input-group input-group-sm">
                  <input name="percent" type="number" step="0.5" min="0" max="100"
                         class="form-control" placeholder="0" form="discForm">
                  <span class="input-group-text">%</span>
                </div>
              </div>
              <div class="col-8">
                <input name="reason" class="form-control form-control-sm" form="discForm"
                       maxlength="250" placeholder="Why? e.g. chipped veneer">
              </div>
            </div>
            <button class="btn btn-sm btn-outline-danger w-100 mt-2" form="discForm">
              <i class="bi bi-send"></i> Request approval</button>
          </div>
        <?php endif; ?>

        <div class="totals-row grand">
          <span>TOTAL</span><span id="totalLbl"><?= peso($total) ?></span>
        </div>

        <div class="row g-2 mt-2">
          <div class="col-6">
            <label class="form-label mb-1 small fw-semibold">Amount received</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">&#8369;</span>
              <input aria-label="Amount received" name="amount_paid" id="paid" type="number" step="0.01" min="0"
                     class="form-control" placeholder="0.00" <?= $cart ? 'required' : '' ?>>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label mb-1 small fw-semibold">Change</label>
            <div class="form-control form-control-sm bg-light fw-bold text-success" id="changeLbl">&#8369;0.00</div>
          </div>
        </div>

        <div class="d-flex gap-1 mt-2 flex-wrap" id="quickCash"></div>

        <div class="mt-2">
          <label class="form-label mb-1 small fw-semibold">Customer name <span class="text-muted">(optional)</span></label>
          <input aria-label="Customer name" maxlength="100" name="customer_name" class="form-control form-control-sm" placeholder="Walk-in customer">
        </div>

        <button class="btn btn-success w-100 mt-3 py-2 fw-semibold" <?= $cart ? '' : 'disabled' ?>>
          <i class="bi bi-check2-circle"></i> Complete Sale &amp; Print Receipt
        </button>
        <button type="submit" form="clearForm" formnovalidate class="btn btn-link w-100 text-muted mt-1" <?= $cart ? '' : 'disabled' ?>>Clear cart</button>


        <div class="text-muted-sm mt-2 text-center">
          <i class="bi bi-info-circle"></i> Stock is deducted automatically once the sale is completed.
        </div>
      </form>
    </div>
  </div>
</div>

<form id="clearForm" method="post" action="<?= url('pos.clear') ?>" class="d-none"><?= csrf_field() ?></form>
<?php
$subJs  = js_json(round($subtotal, 2));
$discJs = js_json(round($discount, 2));
$lookupUrl = js_json(url('inventory.lookup'));
$addUrl    = js_json(url('pos.add'));
$scanUrl   = js_json(url('pos.scan'));
$tokenJs = js_json(csrf_token());
$inlineScript = <<<JS
function submitScan(action, key, value) {
  var form = document.createElement('form'); form.method = 'post'; form.action = action;
  var values = {_token: {$tokenJs}}; values[key] = value;
  Object.keys(values).forEach(function(name) { var input = document.createElement('input');
    input.type = 'hidden'; input.name = name; input.value = values[name]; form.appendChild(input); });
  document.body.appendChild(form); form.submit();
}
var SUBTOTAL = {$subJs};
var DISCOUNT = {$discJs};

function money(v) {
  return '₱' + Number(v).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}

// FR-04b : the discount is not something typed here any more. It is whatever
// the office has approved for this till, worked out server-side and handed
// down as DISCOUNT - so the browser cannot talk the total downwards.
function recalc() {
  var total = Math.round((SUBTOTAL - DISCOUNT) * 100) / 100;
  var paid  = parseFloat(document.getElementById('paid').value) || 0;
  var chg   = Math.round((paid - total) * 100) / 100;

  document.getElementById('totalLbl').innerHTML = money(total);
  var lbl = document.getElementById('changeLbl');
  lbl.innerHTML = money(chg < 0 ? 0 : chg);
  lbl.className = 'form-control form-control-sm bg-light fw-bold ' +
                  (paid > 0 && chg < 0 ? 'text-danger' : 'text-success');

  // Suggested cash denominations for fast change handling.
  var box = document.getElementById('quickCash');
  box.innerHTML = '';
  if (total <= 0) return;
  var opts = [Math.ceil(total / 100) * 100, Math.ceil(total / 500) * 500, Math.ceil(total / 1000) * 1000];
  opts.push(total);
  opts.filter(function (v, i, a) { return v > 0 && a.indexOf(v) === i; })
      .sort(function (a, b) { return a - b; })
      .slice(0, 4)
      .forEach(function (v) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-outline-secondary btn-sm py-0 px-2';
        b.style.fontSize = '.72rem';
        b.textContent = money(v);
        b.onclick = function () { document.getElementById('paid').value = v.toFixed(2); recalc(); };
        box.appendChild(b);
      });
}

var paidEl = document.getElementById('paid');
if (paidEl) paidEl.addEventListener('input', recalc);
recalc();

// FR-03 : scan a QR label (or type a SKU) to add the item straight to the cart.
var scan = document.getElementById('scanBox');
function rejectScan() {
  if (!scan) return;
  scan.classList.add('is-invalid');
  scan.select();
  setTimeout(function () { scan.classList.remove('is-invalid'); }, 1400);
}
if (scan) {
  scan.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var code = scan.value.trim();
    if (!code) return;
    // The lookup answers with kind 'unit' (a barcode on one physical piece) or
    // kind 'product' (a SKU covering every piece of that item). They are NOT
    // interchangeable: reading d.product.id on a unit answer throws, which is
    // why scanning a piece label used to do nothing at all. Route on kind, so
    // pressing Enter adds exactly what the Add piece button would add.
    fetch({$lookupUrl} + '&code=' + encodeURIComponent(code))
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.found && d.kind === 'unit' && d.unit) {
          submitScan({$scanUrl}, 'code', d.unit.code);
        } else if (d && d.found && d.kind === 'product' && d.product) {
          submitScan({$addUrl}, 'id', d.product.id);
        } else {
          rejectScan();
        }
      })
      .catch(function () { rejectScan(); });
  });
}
JS;
?>
