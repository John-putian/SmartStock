<?php
/**
 * FR-11 - the notification centre.
 * Everything on this page belongs to the signed-in account alone.
 */
$pills = [
  ''       => ['Everything', 'collection'],
  'unread' => ['Unread',     'envelope'],
  'read'   => ['Read',       'envelope-open'],
];
?>

<div class="d-flex flex-wrap gap-2 mb-3 no-print align-items-center">
  <?php foreach ($pills as $key => [$label, $icon]): ?>
    <a class="btn btn-sm <?= $status === $key ? 'btn-primary' : 'btn-outline-secondary' ?>"
       href="<?= url('notifications', array_filter(['status' => $key, 'type' => $type])) ?>">
      <i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?>
      <?= $key === 'unread' && $unread ? ' <span class="badge bg-danger rounded-pill">' . num($unread) . '</span>' : '' ?>
    </a>
  <?php endforeach; ?>

  <span class="vr mx-1 d-none d-md-inline"></span>

  <a class="btn btn-sm <?= $type === '' ? 'btn-dark' : 'btn-outline-secondary' ?>"
     href="<?= url('notifications', array_filter(['status' => $status])) ?>">All kinds</a>
  <?php foreach (Notification::TYPES as $key => [$label, $icon]):
        $n = $counts[$key] ?? 0; ?>
    <a class="btn btn-sm <?= $type === $key ? 'btn-dark' : 'btn-outline-secondary' ?>"
       href="<?= url('notifications', array_filter(['status' => $status, 'type' => $key])) ?>">
      <i class="bi bi-<?= $icon ?>"></i> <?= e($label) ?>
      <?= $n ? ' <span class="badge badge-soft-danger">' . num($n) . '</span>' : '' ?>
    </a>
  <?php endforeach; ?>

  <?php if ($unread): ?>
    <form method="post" action="<?= url('notifications.read') ?>" class="ms-auto">
      <?= csrf_field() ?>
      <button class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-check2-all"></i> Mark all as read</button>
    </form>
  <?php endif; ?>
</div>

<div class="alert alert-info d-flex gap-3 align-items-start no-print">
  <i class="bi bi-bell fs-4"></i>
  <div>
    <strong>What needs your attention.</strong>
    The audit trail records everything that happened; this page carries only the
    things somebody has to act on, addressed to the person who has to act.
    <?php if (Auth::canApprove()): ?>
      As <?= e(role_label(Auth::role())) ?> you receive stock warnings, price
      requests waiting on a decision, discounts authorised at the till, and the
      end-of-day takings.
    <?php else: ?>
      You receive the outcome of any price change you have asked for.
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-bell"></i> Notifications
    <span class="text-muted-sm">&middot; <?= num(count($items)) ?> shown<?= $unread ? ', ' . num($unread) . ' unread' : '' ?></span>
  </div>

  <?php if (!$items): ?>
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-check2-circle fs-1 d-block mb-2"></i>
      Nothing here. Everything that needed attention has been dealt with.
    </div>
  <?php else: ?>
    <div>
      <?php foreach ($items as $n):
        $meta = Notification::TYPES[$n['type']] ?? ['Notice', 'bell'];
        $tone = ['danger' => 'danger', 'warning' => 'warning',
                 'success' => 'success', 'info' => 'info'][$n['severity']] ?? 'secondary';
        $href = url('notifications.open', ['id' => $n['id']]); ?>
        <div class="notif-row<?= $n['read_at'] ? '' : ' is-unread' ?>">
          <span class="badge badge-soft-<?= $tone ?> notif-ico">
            <i class="bi bi-<?= e($meta[1]) ?>"></i></span>

          <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-baseline gap-2">
              <a class="fw-semibold text-decoration-none" href="<?= e($href) ?>">
                <?= e($n['title']) ?></a>
              <span class="badge badge-soft-secondary"><?= e($meta[0]) ?></span>
              <?php if (!$n['read_at']): ?>
                <span class="badge badge-soft-danger">New</span>
              <?php endif; ?>
            </div>
            <?php if ($n['body']): ?>
              <div class="text-muted-sm mt-1"><?= e($n['body']) ?></div>
            <?php endif; ?>
            <div class="text-muted-sm mt-1">
              <?= date('l, F j, Y \a\t g:i A', strtotime($n['created_at'])) ?>
              <?php if ($n['read_at']): ?>
                &middot; read <?= date('M j, g:i A', strtotime($n['read_at'])) ?>
              <?php endif; ?>
            </div>
          </div>

          <?php if (!empty($n['link_page'])): ?>
            <div class="no-print align-self-center">
              <a class="btn btn-sm btn-outline-secondary" href="<?= e($href) ?>">
                Open <i class="bi bi-arrow-right"></i></a>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
