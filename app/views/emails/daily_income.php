<?php
/**
 * FR-11b - the end-of-day takings email.
 *
 * Written the way email has to be written rather than the way a web page is:
 * tables for layout, inline styles on every element, no external stylesheet
 * and no web fonts. Gmail strips <style> blocks in some clients and Outlook
 * ignores most modern CSS, so anything that matters is inlined here.
 *
 * Expects: $day, $revenue, $txn, $pieces, $bestCategory, $bestRevenue,
 *          $discounts, $link, $preview (bool)
 */
$ink   = '#101010';
$red   = '#c41114';
$paper = '#faf7f2';
$line  = '#e8e2d9';
$muted = '#6b6560';

$peso  = function ($v) { return "\u{20B1}" . number_format((float) $v, 2); };
$cell  = 'font-family:Georgia,\'Times New Roman\',serif;font-size:15px;color:' . $ink . ';';
$small = 'font-family:Arial,Helvetica,sans-serif;font-size:12px;color:' . $muted . ';';
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Takings for <?= e($day) ?></title></head>
<body style="margin:0;padding:0;background:#f1efeb;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1efeb;padding:24px 12px;">
<tr><td align="center">

  <table width="560" cellpadding="0" cellspacing="0" border="0"
         style="max-width:560px;width:100%;background:<?= $paper ?>;border:1px solid <?= $line ?>;">

    <!-- masthead -->
    <tr><td style="background:<?= $ink ?>;padding:22px 28px;">
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:19px;
                  letter-spacing:.14em;color:#ffffff;font-weight:bold;">SMART STOCK</div>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;
                  letter-spacing:.08em;color:<?= $red ?>;padding-top:3px;">A&amp;A SURPLUS</div>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:11px;
                  color:#9b9590;padding-top:8px;">
        Household Appliances Trading &middot; Panabo City, Davao del Norte</div>
    </td></tr>

    <?php if (!empty($preview)): ?>
    <tr><td style="background:#fff6d8;border-bottom:1px solid <?= $line ?>;padding:10px 28px;
                   font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#7a5c00;">
      <strong>This is a preview.</strong> The real message carries the same
      figures, without this strip.
    </td></tr>
    <?php endif; ?>

    <!-- headline -->
    <tr><td style="padding:28px 28px 6px 28px;">
      <div style="<?= $small ?>text-transform:uppercase;letter-spacing:.1em;">
        End of day &middot; <?= e($day) ?></div>
      <div style="font-family:Georgia,'Times New Roman',serif;font-size:34px;
                  color:<?= $ink ?>;padding:6px 0 2px 0;"><?= $peso($revenue) ?></div>
      <div style="<?= $small ?>">
        taken across <?= (int) $txn ?> sale<?= (int) $txn === 1 ? '' : 's' ?>,
        <?= (int) $pieces ?> piece<?= (int) $pieces === 1 ? '' : 's' ?> off the floor
      </div>
    </td></tr>

    <tr><td style="padding:18px 28px 0 28px;">
      <table width="100%" cellpadding="0" cellspacing="0" border="0"
             style="border-top:1px solid <?= $line ?>;">

        <tr>
          <td style="<?= $cell ?>padding:12px 0;border-bottom:1px solid <?= $line ?>;width:58%;">
            Sales recorded</td>
          <td align="right" style="<?= $cell ?>padding:12px 0;border-bottom:1px solid <?= $line ?>;">
            <?= (int) $txn ?></td>
        </tr>
        <tr>
          <td style="<?= $cell ?>padding:12px 0;border-bottom:1px solid <?= $line ?>;">
            Pieces sold</td>
          <td align="right" style="<?= $cell ?>padding:12px 0;border-bottom:1px solid <?= $line ?>;">
            <?= (int) $pieces ?></td>
        </tr>
        <?php if (!empty($bestCategory)): ?>
        <tr>
          <td style="<?= $cell ?>padding:12px 0;border-bottom:1px solid <?= $line ?>;">
            Best category<br>
            <span style="<?= $small ?>"><?= e($bestCategory) ?></span></td>
          <td align="right" style="<?= $cell ?>padding:12px 0;border-bottom:1px solid <?= $line ?>;">
            <?= $peso($bestRevenue) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
          <td style="<?= $cell ?>padding:12px 0;border-bottom:1px solid <?= $line ?>;">
            Discounts given
            <?php if ((float) $discounts > 0): ?>
              <br><span style="<?= $small ?>">each one authorised by an
              Store Manager</span>
            <?php endif; ?></td>
          <td align="right" style="<?= $cell ?>padding:12px 0;border-bottom:1px solid <?= $line ?>;
                     color:<?= (float) $discounts > 0 ? $red : $ink ?>;">
            <?= (float) $discounts > 0 ? '-' . $peso($discounts) : $peso(0) ?></td>
        </tr>
      </table>
    </td></tr>

    <!-- call to action -->
    <tr><td style="padding:24px 28px 28px 28px;">
      <table cellpadding="0" cellspacing="0" border="0"><tr>
        <td style="background:<?= $red ?>;">
          <a href="<?= e($link) ?>"
             style="display:inline-block;padding:12px 22px;font-family:Arial,Helvetica,sans-serif;
                    font-size:14px;color:#ffffff;text-decoration:none;font-weight:bold;">
            Open the full report</a>
        </td>
      </tr></table>
      <div style="<?= $small ?>padding-top:12px;line-height:1.5;">
        The link opens Smart Stock on the shop computer. Sales by Category
        breaks this day down further, and shows what to ask for in the next
        container.
      </div>
    </td></tr>

    <!-- footer -->
    <tr><td style="background:#f3efe9;border-top:1px solid <?= $line ?>;padding:16px 28px;">
      <div style="<?= $small ?>line-height:1.6;">
        Sent automatically by Smart Stock the first time the system is opened
        each day. You are receiving it because your address is on the
        administrator account.<br>
        <span style="color:#9b9590;">Inventory, Sales &amp; Decision Support System
        &middot; IT 307 &middot; Duque &middot; Dizon &middot; Puti-an</span>
      </div>
    </td></tr>

  </table>

</td></tr>
</table>
</body>
</html>
