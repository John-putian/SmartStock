<?php
/**
 * Help - written for somebody who has never seen the system before.
 * Short sentences, no jargon, and the steps in the order you do them.
 */
$labels = ['cashier' => 'Cashier', 'staff' => 'Work Staff',
           'manager' => 'Store Manager', 'admin' => 'Administrator'];

/** The how-to that matches whoever is reading. */
$guides = [
  'cashier' => [
    'You are the Cashier. You run the till.',
    [
      ['Sell something', [
        'Click <strong>Point of Sale</strong> in the menu on the left.',
        'Find the item: click its picture, or scan the barcode on its label.',
        'It appears under <strong>Current Sale</strong> on the right.',
        'Type what the customer handed you into <strong>Amount received</strong>. The change works itself out.',
        'Click <strong>Complete Sale &amp; Print Receipt</strong>. The stock goes down by itself.',
      ]],
      ['Ask for a discount', [
        'Put the items in the sale first.',
        'In the box marked <strong>Ask for a discount</strong>, type the percentage &mdash; 10 means ten percent off.',
        'Say why. &ldquo;Chipped on the side&rdquo; is enough.',
        'Click <strong>Request approval</strong>. The manager gets a notification.',
        'The sale waits. You cannot finish it until they answer.',
        'When they say yes, the discount lands on your sale. You apply it by completing the sale, and it prints on the receipt.',
      ]],
      ['Print a receipt again', [
        'Open <strong>Point of Sale</strong> and find the sale, or use the link you were given when you completed it.',
        'Only you can reprint a receipt you issued. Another cashier cannot, and neither can the administrator.',
        'A manager can look at one for a dispute, but their copy is stamped <strong>Office copy</strong> so it cannot be handed to a customer.',
      ]],
      ['If something is wrong', [
        'Wrong item in the sale? Click <strong>remove</strong> beside it.',
        'Start over? Click <strong>Clear cart</strong> at the bottom.',
        'Cannot find an item? It may be sold out &mdash; check <strong>Stock Alerts</strong>.',
      ]],
    ],
  ],
  'staff' => [
    'You are Work Staff. You look after the stock on the floor.',
    [
      ['List something that came in', [
        'Click <strong>Inventory</strong>, then <strong>Add Item</strong>.',
        'Fill in the name, pick a category, and set the price.',
        'Take a photo of the actual piece and upload it &mdash; every second-hand item is different.',
        'Save. The system makes a barcode for each piece you can print and stick on.',
      ]],
      ['Put more of something on the floor', [
        'Open the item, then click <strong>Receive Stock</strong>.',
        'Type how many arrived. The count goes up and it is written in the record.',
      ]],
      ['Change a price', [
        'Putting a price <strong>up</strong>: just edit it and save. It takes effect at once.',
        'Bringing a price <strong>down</strong>: type the new price and say why. It is sent to the manager.',
        'The item keeps its old price until they agree. Watch <strong>My Requests</strong> for the answer.',
      ]],
    ],
  ],
  'manager' => [
    'You are the Store Manager. You decide what the floor is not allowed to decide.',
    [
      ['Answer a discount request', [
        'The bell at the top right turns red when a till is waiting on you.',
        'Click <strong>Price Approvals</strong>. Discount requests are at the top &mdash; a sale is frozen for each one.',
        'You can see the percentage, the cart total, and exactly how much money it takes off.',
        'Click <strong>Approve</strong> or the red <strong>&times;</strong>. Add a note if you want to explain.',
        'The cashier is told straight away and can finish the sale.',
      ]],
      ['Answer a price request', [
        'Same screen, lower down. You see the listed price, the price asked for, and the reason.',
        'Approving writes the new price onto the item immediately.',
        'Rejecting leaves the price exactly where it is.',
      ]],
      ['See how the shop is doing', [
        '<strong>Reports</strong> &mdash; takings by day, week, month or year.',
        '<strong>Sales by Category</strong> &mdash; what to ask for in the next container.',
        '<strong>Stock Alerts</strong> &mdash; what has run out or is about to.',
      ]],
    ],
  ],
  'admin' => [
    'You are the Administrator. You manage accounts and oversee the system. Discount decisions belong only to the Store Manager.',
    [
      ['Add somebody to the system', [
        'Click <strong>User Accounts</strong>, then <strong>Add User</strong>.',
        'Choose their role carefully &mdash; it decides what they can reach. The table below shows exactly what each one gets.',
        'Give them a password and tell them to change it.',
      ]],
      ['Turn on the email notifications', [
        'Click <strong>Email Notifications</strong>.',
        'The steps for getting a Gmail app password are written on that page.',
        'Use <strong>Open the preview</strong> to see the email without sending anything.',
      ]],
      ['Check who did what', [
        '<strong>Audit Trail</strong> records every action with a name and a time.',
        'It also records every attempt to reach a page somebody was not allowed to.',
      ]],
    ],
  ],
];
[$intro, $sections] = $guides[$me] ?? $guides['cashier'];
?>

<div class="alert alert-primary d-flex gap-3 align-items-start no-print">
  <i class="bi bi-life-preserver fs-3"></i>
  <div>
    <div class="fs-5 fw-bold"><?= e($intro) ?></div>
    <div>Everything below is written for your account. Sign in as somebody
    else and this page changes to match them.</div>
  </div>
</div>

<a class="card text-decoration-none mb-3 d-block" href="<?= url('help.connect') ?>">
  <div class="card-body d-flex align-items-center gap-3">
    <i class="bi bi-phone fs-3 text-primary"></i>
    <div class="flex-grow-1">
      <div class="fw-bold text-body">Open Smart Stock on a phone</div>
      <div class="text-muted-sm">
        The address to type and the code to scan, read from this laptop as it is right
        now &mdash; so it still works after the Wi-Fi address changes.
      </div>
    </div>
    <i class="bi bi-chevron-right text-muted"></i>
  </div>
</a>

<div class="row g-3">
  <?php foreach ($sections as [$title, $steps]): ?>
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header"><i class="bi bi-list-check"></i> <?= e($title) ?></div>
        <div class="card-body">
          <ol class="mb-0" style="line-height:1.85;padding-left:1.1rem">
            <?php foreach ($steps as $step): ?>
              <li><?= $step ?></li>
            <?php endforeach; ?>
          </ol>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card mt-3">
  <div class="card-header">
    <i class="bi bi-people"></i> Who is allowed to do what
    <span class="text-muted-sm">&middot; read straight from the system's own
    access rules, so it cannot be out of date</span>
  </div>

  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th style="min-width:18rem">Can they&hellip;</th>
          <?php foreach ($roles as $r): ?>
            <th class="text-center <?= $r === $me ? 'table-primary' : '' ?>">
              <?= e($labels[$r]) ?>
              <?= $r === $me ? '<div class="text-muted-sm fw-normal">you</div>' : '' ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($matrix as $group => $rows): ?>
          <tr class="table-light">
            <td colspan="<?= count($roles) + 1 ?>" class="fw-bold small text-uppercase"
                style="letter-spacing:.08em"><?= e($group) ?></td>
          </tr>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e($row['label']) ?></td>
              <?php foreach ($roles as $r):
                    $yes = in_array($r, $row['roles'], true); ?>
                <td class="text-center <?= $r === $me ? 'table-primary' : '' ?>">
                  <?php if ($yes): ?>
                    <i class="bi bi-check-lg text-success fs-5"></i>
                  <?php else: ?>
                    <span class="text-muted">&mdash;</span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>

        <tr class="table-light">
          <td colspan="<?= count($roles) + 1 ?>" class="fw-bold small text-uppercase"
              style="letter-spacing:.08em">Money</td>
        </tr>
        <?php foreach ($powers as [$label, $who, $note]): ?>
          <tr>
            <td>
              <?= e($label) ?>
              <?php if ($note): ?>
                <div class="text-muted-sm"><?= e($note) ?></div>
              <?php endif; ?>
            </td>
            <?php foreach ($roles as $r): ?>
              <td class="text-center <?= $r === $me ? 'table-primary' : '' ?>">
                <?php if (in_array($r, $who, true)): ?>
                  <i class="bi bi-check-lg text-success fs-5"></i>
                <?php else: ?>
                  <span class="text-muted">&mdash;</span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card-body text-muted-sm">
    <i class="bi bi-shield-lock"></i>
    A dash is not just a missing menu link. If somebody types the address by
    hand they are still turned away, and the attempt is written into the audit
    trail with their name on it.
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><i class="bi bi-upc-scan"></i> Photographing an item vs scanning its label</div>
  <div class="card-body">
    <div class="row g-4">
      <div class="col-md-6">
        <div class="fw-bold"><i class="bi bi-camera"></i> Taking a product photo</div>
        <div class="text-muted-sm">
          On <strong>Add item</strong>. It records what the piece looks like, and the picture is
          stored with the item. Use <strong>Take photo</strong> to open the camera, or
          <strong>Choose from gallery</strong> for a picture already on the device; if the browser
          will not open the camera you get the ordinary file picker instead. An iPhone HEIC photo,
          or any large camera file, is turned into a modest JPG on the phone itself before it is
          sent &mdash; you will see a line such as &ldquo;Photo prepared: 4.1&nbsp;MB &rarr;
          380&nbsp;KB&rdquo;, and that smaller copy is what is saved on the item. The photo never
          reads a code and never sets a price.
        </div>
      </div>
      <div class="col-md-6">
        <div class="fw-bold"><i class="bi bi-upc-scan"></i> Scanning a QR label</div>
        <div class="text-muted-sm">
          At the <strong>till</strong>. The scan box reads <strong>typed characters</strong>: a USB
          or Bluetooth barcode scanner works like a keyboard, typing the code and pressing Enter.
          <strong>Smart Stock never uses a camera to read codes</strong> &mdash; there is no
          camera scanning anywhere in the system. With no scanner attached, type the code from the
          label and press Enter, or tap the item&rsquo;s card.
        </div>
      </div>
      <div class="col-12">
        <div class="fw-bold">What the two codes mean</div>
        <div class="text-muted-sm">
          A <strong>piece code</strong> such as <code>AA-FUR-0007-03</code> is printed on one
          physical piece and sells exactly that piece. An <strong>item SKU</strong> such as
          <code>AA-FUR-0007</code> covers every piece of that item, and takes the oldest one off the
          floor. The scan box accepts either and tells them apart; an unknown code flashes the field
          red and adds nothing.
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header"><i class="bi bi-question-circle"></i> Common questions</div>
  <div class="card-body">
    <div class="row g-4">
      <div class="col-md-6">
        <div class="fw-bold">Why can I not see the Point of Sale?</div>
        <div class="text-muted-sm">Only the Cashier sells. Every invoice records
        who was behind the counter, so selling is tied to that one role.</div>
      </div>
      <div class="col-md-6">
        <div class="fw-bold">Why will the sale not complete?</div>
        <div class="text-muted-sm">A discount is waiting on an answer. Either wait
        for it, or withdraw the request and sell at full price.</div>
      </div>
      <div class="col-md-6">
        <div class="fw-bold">Why is the discount a percentage?</div>
        <div class="text-muted-sm">Because a cart changes. &ldquo;10% off&rdquo;
        still means the same thing after another item is added; &ldquo;&#8369;500
        off&rdquo; quietly becomes a different bargain.</div>
      </div>
      <div class="col-md-6">
        <div class="fw-bold">I changed a price and nothing happened.</div>
        <div class="text-muted-sm">You lowered it. Lowering a listed price needs
        the manager. Look under <strong>My Requests</strong> &mdash; it is there,
        waiting.</div>
      </div>
      <div class="col-md-6">
        <div class="fw-bold">Two people cannot sign in on one browser.</div>
        <div class="text-muted-sm">One browser holds one sign-in. Use a second
        browser, or a private window, for the second person.</div>
      </div>
      <div class="col-md-6">
        <div class="fw-bold">Something is wrong with the stock count.</div>
        <div class="text-muted-sm">Open the item and read its movement history.
        Every change is recorded with who made it and why.</div>
      </div>
    </div>
  </div>
</div>
