<?php
/** Shared page chrome: sidebar navigation + top bar. */
$u        = Auth::user();
$current  = $GLOBALS['current_page'] ?? '';
$alertNum = (int) Database::value(
    'SELECT COUNT(*) FROM products WHERE status="active" AND quantity <= reorder_level');

// Price reductions waiting on a decision. An approver sees the whole queue;
// work staff see only how many of their own asks are still unanswered.
$priceNum = Auth::canApprove()
    ? PriceRequest::pendingCount() + DiscountRequest::pendingCount()
    : PriceRequest::pendingCountFor((int) Auth::id())
      + DiscountRequest::pendingCountFor((int) Auth::id());

// FR-11 - unread notices for this account, and the few shown under the bell.
$notifNum  = Notification::unreadCount((int) Auth::id());
$notifTop  = Notification::forUser((int) Auth::id(), ['limit' => 6]);
$initials = strtoupper(mb_substr($u['full_name'], 0, 1) .
            mb_substr(strstr($u['full_name'], ' ') ?: '', 1, 1));

/** Nav item helper. */
/**
 * A sidebar link. A key without a dot ("reports") lights up for every page in
 * its group, except the ones listed in $notWhen - those have a nav entry of
 * their own and would otherwise highlight two items at once. A key with a dot
 * ("reports.category") only ever matches itself.
 */
function nav_item(string $page, string $icon, string $label, string $current,
                  ?string $badge = null, string $badgeClass = 'bg-danger',
                  array $notWhen = []): void
{
    $group  = explode('.', $current)[0];
    $isOwn  = strpos($page, '.') !== false;
    $match  = $isOwn
        ? ($current === $page)
        : ($group === $page && !in_array($current, $notWhen, true));
    $active = $match ? ' active' : '';
    echo '<a class="' . trim($active) . '" href="' . url($page) . '">'
       . '<i class="bi bi-' . $icon . '"></i><span>' . e($label) . '</span>'
       . ($badge !== null ? '<span class="badge ' . $badgeClass . ' rounded-pill">' . e($badge) . '</span>' : '')
       . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?> &middot; <?= e(APP_SHORT) ?></title>
<link rel="icon" href="<?= base_url('assets/img/favicon.png') ?>" type="image/png">

<?php /* Phone home-screen install. Android reads the manifest; iOS reads the
         apple-* tags below, which is why both are here. */ ?>
<link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
<meta name="theme-color" content="#C41114">
<meta name="application-name" content="Smart Stock">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Smart Stock">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" href="<?= base_url('assets/img/apple-touch-icon.png') ?>">

<link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/vendor/icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
</head>
<body>

<aside class="aa-sidebar" id="aaSidebar">
  <div class="aa-brand">
    <a href="<?= url('dashboard') ?>">
      <img src="<?= base_url('assets/img/logo.png') ?>" alt="Smart Stock — A&amp;A Surplus">
    </a>
    <div class="wordmark">Smart Stock<em>A&amp;A Surplus</em></div>
    <small>Inventory, Sales &amp; Decision Support<br>Panabo City, Davao del Norte</small>
  </div>

  <nav class="aa-nav">
    <div class="label">Operations</div>
    <?php
      nav_item('dashboard', 'speedometer2', 'Dashboard', $current);
      // FR-04 - the till is the cashier's station; nobody else gets the link,
      // and the router turns the rest away even if the address is typed.
      if (Auth::isTill()) {
        nav_item('pos', 'cart3', 'Point of Sale', $current);
      }
      nav_item('inventory', 'box-seam', 'Inventory', $current);
      nav_item('alerts', 'exclamation-triangle', 'Stock Alerts', $current,
               $alertNum ? (string) $alertNum : null);

      nav_item('notifications', 'bell', 'Notifications', $current,
               $notifNum ? (string) $notifNum : null);

      nav_item('help', 'life-preserver', 'Help', $current);

      // The floor follows its own price asks from here. Approvers get the
      // queue itself, further down under Administration.
      if (!Auth::canApprove()) {
        nav_item('approvals', 'tag', 'My Requests', $current,
                 $priceNum ? (string) $priceNum : null, 'bg-warning text-dark');
      }
    ?>

    <?php /* Insights are the office's. A cashier watches stock move; reading
             the takings and the analytics is not their job, and
             the router turns them away even if they type the address. */ ?>
    <?php if (Auth::canManage()): ?>
      <div class="label">Insights</div>
      <?php
        nav_item('reports', 'file-earmark-bar-graph', 'Reports', $current,
                 null, 'bg-danger', ['reports.category']);
        nav_item('reports.category', 'tags-fill', 'Sales by Category', $current);
        nav_item('analytics', 'graph-up-arrow', 'Sales Analytics', $current);
      ?>

      <div class="label">Administration</div>
      <?php
        nav_item('categories', 'tags', 'Categories', $current);
        nav_item('approvals', 'patch-check', 'Price Approvals', $current,
                 $priceNum ? (string) $priceNum : null, 'bg-warning text-dark');
        if (Auth::isAdmin()) {
          nav_item('users', 'people', 'User Accounts', $current);
          nav_item('mail', 'envelope-at', 'Email Notifications', $current);
        }
        nav_item('audit', 'shield-check', 'Audit Trail', $current);
      ?>
    <?php endif; ?>
  </nav>

  <div class="aa-user">
    <div class="avatar"><?= e($initials) ?></div>
    <div class="flex-grow-1 overflow-hidden">
      <div class="name text-truncate"><?= e($u['full_name']) ?></div>
      <div class="role"><?= e(role_label(Auth::role())) ?></div>
    </div>
    <form method="post" action="<?= url('logout') ?>" class="d-inline">
<?= csrf_field() ?>
<button type="submit"  class="text-secondary" title="Sign out"><i class="bi bi-box-arrow-right fs-5"></i></button></form>
  </div>
</aside>

<main class="aa-main">
  <div class="aa-topbar">
    <button class="btn btn-sm btn-outline-secondary d-lg-none" aria-label="Toggle navigation" aria-controls="aaSidebar" aria-expanded="false" onclick="this.setAttribute('aria-expanded',document.getElementById('aaSidebar').classList.toggle('open'))">
      <i class="bi bi-list"></i>
    </button>
    <div class="flex-grow-1">
      <h1><?= e($pageTitle) ?></h1>
      <div class="sub"><?= e(APP_CLIENT) ?>
        &middot; <?= date('l, F j, Y') ?></div>
    </div>
    <?php /* FR-11 - notification bell. The count is this account's unread
             notices, not a store-wide figure, so two people at two tills see
             their own. */ ?>
    <div class="dropdown no-print me-2">
      <button class="btn btn-sm btn-outline-secondary position-relative" type="button"
              data-bs-toggle="dropdown" data-bs-auto-close="outside"
              aria-expanded="false" title="Notifications">
        <i class="bi bi-bell<?= $notifNum ? '-fill' : '' ?>"></i>
        <?php if ($notifNum): ?>
          <span class="badge bg-danger rounded-pill notif-dot"><?= $notifNum > 9 ? '9+' : $notifNum ?></span>
        <?php endif; ?>
      </button>

      <div class="dropdown-menu dropdown-menu-end notif-menu shadow">
        <div class="d-flex align-items-center px-3 py-2 border-bottom">
          <span class="fw-semibold">Notifications</span>
          <span class="text-muted-sm ms-2"><?= $notifNum ? $notifNum . ' unread' : 'all caught up' ?></span>
          <a class="ms-auto small" href="<?= url('notifications') ?>">See all</a>
        </div>

        <?php if (!$notifTop): ?>
          <div class="px-3 py-4 text-center text-muted-sm">
            <i class="bi bi-check2-circle fs-4 d-block mb-1"></i>
            Nothing needs your attention.
          </div>
        <?php endif; ?>

        <?php foreach ($notifTop as $n):
          $meta = Notification::TYPES[$n['type']] ?? ['Notice', 'bell'];
          $tone = ['danger' => 'danger', 'warning' => 'warning',
                   'success' => 'success', 'info' => 'info'][$n['severity']] ?? 'secondary'; ?>
          <a class="dropdown-item notif-item<?= $n['read_at'] ? '' : ' is-unread' ?>"
             href="<?= url('notifications.open', ['id' => $n['id']]) ?>">
            <div class="d-flex gap-2">
              <span class="badge badge-soft-<?= $tone ?> notif-ico">
                <i class="bi bi-<?= e($meta[1]) ?>"></i></span>
              <span class="flex-grow-1">
                <span class="d-block fw-semibold"><?= e($n['title']) ?></span>
                <?php if ($n['body']): ?>
                  <span class="d-block text-muted-sm"><?= e($n['body']) ?></span>
                <?php endif; ?>
                <span class="d-block text-muted-sm mt-1">
                  <?= date('M j, g:i A', strtotime($n['created_at'])) ?></span>
              </span>
            </div>
          </a>
        <?php endforeach; ?>

        <?php if ($notifNum): ?>
          <form method="post" action="<?= url('notifications.read') ?>" class="px-3 py-2 border-top">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-secondary w-100">
              <i class="bi bi-check2-all"></i> Mark all as read</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if (Auth::isTill()): ?>
      <a href="<?= url('pos') ?>" class="btn btn-sm btn-primary no-print">
        <i class="bi bi-cart-plus"></i> New Sale
      </a>
    <?php endif; ?>
  </div>

  <div class="aa-content">
    <?php /* Named $flashMsg, not $f: this file runs inside view() just before
             the page template, and several templates use $f for their own
             filter array. A short name here would quietly overwrite theirs. */ ?>
    <?php foreach (take_flash() as $flashMsg): ?>
      <div class="alert alert-<?= e($flashMsg['type']) ?> alert-dismissible fade show no-print" role="alert">
        <?= $flashMsg['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>
    <?php unset($flashMsg); ?>
