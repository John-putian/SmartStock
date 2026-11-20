<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Receipt <?= e($sale['invoice_no']) ?></title>
<link rel="icon" href="<?= base_url('assets/img/favicon.png') ?>" type="image/png">
<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
<style>
  /* ------------------------------------------------------------------ print
     A receipt is not a document, it is a slip. Printed at A4 it came out as a
     small bordered card floating in the middle of a wasted sheet, so the page
     itself is sized to an 80mm till roll and the on-screen card chrome - the
     border, the rounded corners, the red rule, the centring margin - is taken
     off, because none of that is meant to survive contact with paper.

     80mm x 180mm is a till-roll page. A length cannot be paired with `auto`
     in @page - Chrome drops the whole declaration - so the height is fixed;
     a longer receipt simply flows onto a second slip.

     On a plain A4 printer, swap the @page line for:
         @page { size: A4 portrait; margin: 12mm; }
     and the slip prints at the top of a normal sheet instead.              */
  @media print {
    @page { size: 80mm 180mm; margin: 4mm; }

    .no-print { display: none !important; }
    body { background: #fff; margin: 0; padding: 0; }

    .receipt {
      border: 0; border-radius: 0; box-shadow: none;
      margin: 0; padding: 0; max-width: none; width: auto;
      font-size: 11.5px;
    }
    .receipt .logo { width: 54px; height: 54px; margin-bottom: 6px; }
    .receipt h2 { font-size: 13px; }

    /* A line item and its unit codes stay on the same piece of paper. */
    .receipt tr { break-inside: avoid; page-break-inside: avoid; }
  }
</style>
</head>
<body>

<?php $copy = !empty($copy); ?>
<div class="text-center pt-4 no-print">
  <?php if ($copy): ?>
    <div class="alert alert-warning d-inline-flex align-items-center gap-2 py-2">
      <i class="bi bi-eye"></i>
      <span><strong>Office copy.</strong> This receipt was issued by the till.
      Reprinting for the customer is the cashier's to do.</span>
    </div>
  <?php else: ?>
    <div class="alert alert-success d-inline-flex align-items-center gap-2 py-2">
      <i class="bi bi-check-circle-fill"></i>
      <span>Sale completed. Inventory has been updated automatically.</span>
    </div>
  <?php endif; ?>
</div>

<div class="receipt">
  <img class="logo" src="<?= base_url('assets/img/logo-sm.png') ?>" alt="A&amp;A Surplus">
  <h2>A&amp;A SURPLUS</h2>
  <div style="text-align:center;font-size:10px;letter-spacing:.14em;margin-bottom:3px">
    SMART STOCK &middot; JAPAN SURPLUS
  </div>
  <div style="text-align:center;font-size:11px">
    <?= e(setting('store_address', 'Panabo City, Davao del Norte')) ?><br>
    Contact: <?= e(setting('store_contact', '0917-000-0000')) ?>
  </div>

  <?php if ($copy): ?>
    <div style="text-align:center;font-size:10px;letter-spacing:.18em;
                border:1px dashed #999;padding:3px;margin:6px 0">
      OFFICE COPY &middot; NOT A SALES RECEIPT
    </div>
  <?php endif; ?>

  <div class="cut"></div>

  <table>
    <tr><td>Invoice</td><td style="text-align:right"><?= e($sale['invoice_no']) ?></td></tr>
    <tr><td>Date</td><td style="text-align:right"><?= date('M j, Y g:i A', strtotime($sale['sale_date'])) ?></td></tr>
    <tr><td>Cashier</td><td style="text-align:right"><?= e($sale['cashier'] ?? '—') ?></td></tr>
    <tr><td>Customer</td><td style="text-align:right"><?= e($sale['customer_name'] ?: 'Walk-in') ?></td></tr>
  </table>

  <div class="cut"></div>

  <table>
    <?php foreach ($items as $it): ?>
      <tr><td colspan="2"><?= e($it['product_name']) ?></td></tr>
      <tr>
        <td style="padding-left:10px;color:#555">
          <?= num($it['quantity']) ?> x <?= number_format((float) $it['unit_price'], 2) ?>
        </td>
        <td style="text-align:right"><?= number_format((float) $it['line_total'], 2) ?></td>
      </tr>
      <?php $codes = $units[(int) $it['product_id']] ?? []; if ($codes): ?>
        <tr><td colspan="2" style="padding-left:10px;font-size:10px;color:#777">
          <?= e(implode('  ', $codes)) ?></td></tr>
      <?php endif; ?>
    <?php endforeach; ?>
  </table>

  <div class="cut"></div>

  <table>
    <tr><td>Subtotal</td><td style="text-align:right"><?= number_format((float) $sale['subtotal'], 2) ?></td></tr>
    <?php if ((float) $sale['discount'] > 0): ?>
      <tr><td>Discount
        <?php if ((float) ($sale['discount_percent'] ?? 0) > 0): ?>
          (<?= rtrim(rtrim(number_format((float) $sale['discount_percent'], 2, '.', ''), '0'), '.') ?>%)
        <?php endif; ?>
      </td><td style="text-align:right">-<?= number_format((float) $sale['discount'], 2) ?></td></tr>
      <tr><td style="font-style:italic">You saved</td>
          <td style="text-align:right;font-style:italic">PHP <?= number_format((float) $sale['discount'], 2) ?></td></tr>
      <?php if (!empty($sale['discount_approver'])): ?>
        <tr><td colspan="2" style="font-size:10px;color:#555">
          Approved by <?= e($sale['discount_approver']) ?>
          (<?= e(role_label((string) $sale['discount_approver_role'])) ?>)</td></tr>
      <?php endif; ?>
    <?php endif; ?>
    <tr style="font-weight:700;font-size:15px">
      <td>TOTAL</td><td style="text-align:right">PHP <?= number_format((float) $sale['total'], 2) ?></td></tr>
    <tr><td><?= e($sale['payment_method']) ?> received</td>
        <td style="text-align:right"><?= number_format((float) $sale['amount_paid'], 2) ?></td></tr>
    <tr><td>Change</td><td style="text-align:right"><?= number_format((float) $sale['change_due'], 2) ?></td></tr>
  </table>

  <div class="cut"></div>

  <div style="text-align:center;font-size:11px;line-height:1.6">
    Items sold are second-hand Japan surplus pieces,<br>
    each identified by the unit code printed above,<br>
    and covered by the store's checking policy.<br><br>
    <strong>Thank you and please come again!</strong><br>
    <span style="font-size:10px;color:#777">Generated by Smart Stock &middot; A&amp;A Surplus</span>
  </div>
</div>

<div class="text-center pb-5 no-print">
  <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print Receipt</button>
  <?php if (Auth::isTill()): ?><a class="btn btn-success" href="<?= url('pos') ?>"><i class="bi bi-cart-plus"></i> New Sale</a><?php endif; ?>
  <?php if (Auth::canManage()): ?><a class="btn btn-outline-secondary" href="<?= url('reports.sales') ?>"><i class="bi bi-list-ul"></i> Sales History</a><?php endif; ?>
</div>

<script src="<?= base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
