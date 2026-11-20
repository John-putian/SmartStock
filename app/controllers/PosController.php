<?php
/**
 * FR-04, FR-05 - point-of-sale checkout and receipts.
 *
 * The cart holds the ids of individual physical units. Every second-hand
 * piece is distinct, so the till always knows exactly which chair, table or
 * vase is going out of the door — not merely how many.
 */
class PosController
{
    /** Unit ids currently in the sale. */
    private static function cart(): array
    {
        return array_values(array_unique(array_map('intval', $_SESSION['cart'] ?? [])));
    }

    private static function setCart(array $cart): void
    {
        $_SESSION['cart'] = array_values(array_unique(array_map('intval', $cart)));
    }

    public static function index(): void
    {
        $q        = trim((string) input('q', ''));
        $category = input('category', '');

        $products = Product::search([
            'q' => $q, 'category' => $category, 'sort' => 'name', 'limit' => 200,
        ]);

        // Expand the cart into display rows, dropping anything no longer sellable.
        $cart  = [];
        $keep  = [];
        $rows  = [];
        $subtotal = 0.0;

        foreach (self::cart() as $unitId) {
            $u = ProductUnit::find($unitId);
            if (!$u || $u['status'] !== 'in_stock' || $u['product_status'] !== 'active') continue;
            $keep[] = $unitId;
            $cart[(int) $u['product_id']][] = $u;
        }
        self::setCart($keep);

        foreach ($cart as $pid => $units) {
            $p     = Product::find($pid);
            if (!$p) continue;
            $qty   = count($units);
            $line  = $qty * (float) $p['selling_price'];
            $subtotal += $line;
            $rows[] = ['p' => $p, 'units' => $units, 'qty' => $qty, 'line' => $line];
        }

        // FR-04b - the till may be waiting on a decision, or holding one it
        // has been given. Either way the cart shows it rather than a price box.
        $req = DiscountRequest::pendingFor((int) Auth::id()) ?? DiscountRequest::approvedFor((int) Auth::id(), self::cart());
        $percent  = ($req && $req['status'] === 'approved' && empty($req['stale'])) ? (float) $req['percent'] : 0.0;
        $discount = round($subtotal * $percent / 100, 2);

        view('pos/index', [
            'products'   => $products,
            'categories' => Category::optionsInUse(),   // only categories with stock
            'q'          => $q,
            'category'   => $category,
            'cart'       => $rows,
            'subtotal'   => $subtotal,
            'request'    => $req,
            'percent'    => $percent,
            'discount'   => $discount,
            'total'      => round($subtotal - $discount, 2),
            'invoice'    => 'Draft sale — number assigned at checkout',
        ], 'Point of Sale');
    }

    /** Add the next available piece of an item (tile click). */
    public static function add(): void
    {
        $id  = (int) input('id');
        $qty = max(1, (int) input('qty', 1));
        $p   = Product::find($id);
        $cart = self::cart();

        if (!$p || $p['status'] !== 'active') {
            flash('danger', 'That item could not be found.');
        } else {
            $units = ProductUnit::available($id, $qty, $cart);
            if (!$units) {
                flash('warning', 'Every piece of <strong>' . e($p['name'])
                    . '</strong> is either sold or already in this sale.');
            } else {
                foreach ($units as $u) { $cart[] = (int) $u['id']; }
                self::setCart($cart);
            }
        }
        redirect('pos', array_filter(['q' => input('q', ''), 'category' => input('category', '')]));
    }

    /** FR-03 - add one exact piece by scanning its barcode. */
    public static function addUnit(): void
    {
        $code = trim((string) input('code', ''));
        $u    = ProductUnit::findByCode($code);
        $cart = self::cart();

        if (!$u) {
            flash('danger', 'No piece is registered under the code <strong>' . e($code) . '</strong>.');
        } elseif (in_array((int) $u['id'], $cart, true)) {
            flash('info', '<strong>' . e($u['unit_code']) . '</strong> is already in this sale.');
        } elseif ($u['status'] === 'sold') {
            flash('warning', '<strong>' . e($u['unit_code']) . '</strong> (' . e($u['name'])
                . ') has already been sold.');
        } elseif ($u['status'] !== 'in_stock' || $u['product_status'] !== 'active') {
            flash('warning', '<strong>' . e($u['unit_code']) . '</strong> is not available for sale.');
        } else {
            $cart[] = (int) $u['id'];
            self::setCart($cart);
            flash('success', 'Added <strong>' . e($u['unit_code']) . '</strong> &mdash; ' . e($u['name']) . '.');
        }
        redirect('pos');
    }

    /** Change how many pieces of an item are in the sale. */
    public static function updateQty(): void
    {
        verify_csrf();
        $cart = self::cart();

        foreach ((array) input('qty', []) as $pid => $want) {
            $pid  = (int) $pid;
            $want = max(0, (int) $want);

            $mine = [];
            foreach ($cart as $unitId) {
                $u = ProductUnit::find($unitId);
                if ($u && (int) $u['product_id'] === $pid) $mine[] = $unitId;
            }
            $have = count($mine);

            if ($want < $have) {                       // hand pieces back
                $drop = array_slice($mine, $want);
                $cart = array_values(array_diff($cart, $drop));
            } elseif ($want > $have) {                 // take more off the floor
                foreach (ProductUnit::available($pid, $want - $have, $cart) as $u) {
                    $cart[] = (int) $u['id'];
                }
            }
        }
        self::setCart($cart);
        redirect('pos');
    }

    /** Remove one specific piece, or every piece of an item. */
    public static function remove(): void
    {
        $cart = self::cart();

        if ($unitId = (int) input('unit', 0)) {
            $cart = array_values(array_diff($cart, [$unitId]));
        } elseif ($pid = (int) input('id', 0)) {
            $cart = array_values(array_filter($cart, function ($uid) use ($pid) {
                $u = ProductUnit::find($uid);
                return !$u || (int) $u['product_id'] !== $pid;
            }));
        }
        self::setCart($cart);
        redirect('pos');
    }

    public static function clear(): void
    {
        self::setCart([]);
        flash('info', 'The cart has been cleared.');
        redirect('pos');
    }

    /**
     * FR-04b - ask the office for money off.
     *
     * The till states a percentage and a reason; it does not state an outcome.
     * Nothing is applied here - the request is written, the office is notified,
     * and the cart stays at full price until somebody rules on it.
     */
    public static function requestDiscount(): void
    {
        verify_csrf();

        $percent = DiscountRequest::clamp((float) input('percent', 0));
        $reason  = trim((string) input('reason', ''));
        $me      = (int) Auth::id();

        if ($percent <= 0) {
            flash('warning', 'Enter how much of a discount you are asking for, as a percentage.');
            redirect('pos');
        }
        if (!self::cart()) {
            flash('warning', 'Put the items in the sale first, then ask for the discount.');
            redirect('pos');
        }
        if (DiscountRequest::pendingFor($me)) {
            flash('info', 'You already have a discount waiting on a decision.');
            redirect('pos');
        }
        if (DiscountRequest::approvedFor($me)) {
            flash('info', 'A discount has already been approved for this sale. '
                . 'Complete it, or cancel the approved discount first.');
            redirect('pos');
        }

        // The subtotal is recomputed here rather than trusted from the form:
        // a figure that decides what the shop gives away is not the browser's
        // to supply.
        $subtotal = 0.0;
        foreach (self::cart() as $unitId) {
            $u = ProductUnit::find($unitId);
            if (!$u || $u['status'] !== 'in_stock') continue;
            $p = Product::find((int) $u['product_id']);
            if ($p) $subtotal += (float) $p['selling_price'];
        }

        // Bind the ask to THIS cart at THESE prices, so the approval that
        // comes back cannot be carried over to a different sale.
        DiscountRequest::open($percent, $subtotal, $reason,
                              DiscountRequest::fingerprint(self::cart()));

        flash('info', '<strong>' . e(DiscountRequest::pct($percent))
            . ' requested.</strong> The sale is held at full price until an '
            . 'Store Manager approves it. Managers have been notified.');
        redirect('pos');
    }

    /** Withdraw an ask, or hand back an approval that is not going to be used. */
    public static function cancelDiscount(): void
    {
        verify_csrf();
        $me = (int) Auth::id();
        $id = (int) input('id');

        if (DiscountRequest::cancel($id, $me)) {
            flash('info', 'The discount request has been withdrawn.');
        } else {
            // An approved one is not deleted - it is a decision somebody made,
            // and the record of it stays. It is simply spent against no sale.
            $r = DiscountRequest::approvedFor($me);
            if ($r && (int) $r['id'] === $id) {
                Database::run('UPDATE discount_requests SET sale_id = NULL,
                               status = "rejected",
                               decision_note = CONCAT(COALESCE(decision_note,""),
                                                      " [not used at the till]")
                               WHERE id = ?', [$id]);
                Audit::log('Sales', 'DISCOUNT_UNUSED',
                    'Approved discount #' . $id . ' was not used');
                flash('info', 'The approved discount has been set aside. '
                    . 'The sale is back at full price.');
            } else {
                flash('warning', 'That discount is no longer yours to change.');
            }
        }
        redirect('pos');
    }

    public static function checkout(): void
    {
        verify_csrf();
        $cart = self::cart();
        if (!$cart) {
            flash('warning', 'Add at least one item before completing the sale.');
            redirect('pos');
        }

        // A discount can only come from an approval this account already holds.
        // Nothing the browser posts decides it.
        $me      = (int) Auth::id();
        $pending = DiscountRequest::pendingFor($me);
        if ($pending) {
            flash('warning', '<strong>Sale not completed.</strong> This sale is waiting on '
                . 'a decision about the ' . e(DiscountRequest::pct((float) $pending['percent']))
                . ' discount. Wait for it, or withdraw the request.');
            redirect('pos');
        }

        $approved = DiscountRequest::approvedFor($me, $cart);

        // The approval was granted against a particular sale. If the cart has
        // changed since, it no longer applies - say so plainly rather than
        // quietly selling at full price or, worse, at the old discount.
        if ($approved && !empty($approved['stale'])) {
            flash('warning', '<strong>Sale not completed.</strong> This sale or the approver changed after '
                . 'the ' . e(DiscountRequest::pct((float) $approved['percent']))
                . ' discount was approved, so that approval no longer covers this sale. '
                . 'Cancel this approval and ask a Store Manager for approval again.');
            redirect('pos');
        }

        // One submission, one sale. The key is tied to the cart as it stands,
        // so a double-click resolves to the sale the first click made instead
        // of trying to sell the same pieces twice.
        $idemKey = substr(hash('sha256',
            $me . '|' . DiscountRequest::fingerprint($cart) . '|'
            . ($approved['id'] ?? 0) . '|' . session_id()), 0, 36);

        $result = Sale::checkout($cart, [
            'discount_request_id' => $approved ? (int) $approved['id'] : 0,
            'idempotency_key'     => $idemKey,
            'amount_paid'    => (float) input('amount_paid', 0),
            'payment_method' => in_array(input('payment_method'), ['Cash', 'Bank Transfer'], true)
                                  ? input('payment_method') : 'Cash',
            'customer_name'  => trim((string) input('customer_name', '')),
        ]);

        if (!$result['ok']) {
            flash('danger', '<strong>Sale not completed.</strong> ' . e($result['error']));
            redirect('pos');
        }

        // The approval was validated, locked and spent inside Sale::checkout's
        // transaction. Nothing to do here any more - doing it after the commit
        // was what let two racing checkouts both claim one approval.

        self::setCart([]);
        flash('success', 'Sale <strong>' . e($result['invoice']) .
              '</strong> completed. The pieces sold have been marked off the floor.'
              . ($approved ? ' ' . e(DiscountRequest::pct((float) $approved['percent']))
                              . ' was taken off, approved by '
                              . e((string) ($approved['decided_by_name'] ?? 'the office')) . '.' : ''));
        redirect('pos.receipt', ['id' => $result['sale_id']]);
    }

    /**
     * FR-05 - the receipt.
     *
     * Printing belongs to the till. The cashier who rang the sale up can open
     * and reprint it; the office may look at one from the sales report, for a
     * dispute or an audit, and that view is stamped as a copy rather than
     * dressed up as the original. Nobody else gets in - not another cashier,
     * not the floor staff. The router can only see roles, so the ownership
     * check has to live here.
     */
    public static function receipt(): void
    {
        $id   = (int) input('id');
        $sale = Sale::find($id);

        // A missing receipt must not bounce the office to the till, which
        // would refuse them and write a security denial against their name.
        $home = Auth::isTill() ? 'pos' : 'reports.sales';

        if (!$sale) {
            flash('danger', 'That receipt could not be found.');
            redirect($home);
        }

        $mine   = (int) $sale['user_id'] === (int) Auth::id();
        $office = Auth::canManage();

        if (!$mine && !$office) {
            Auth::deny('A receipt belongs to the till that issued it. '
                . 'You can only open your own.');
        }

        if (!$mine) {
            Audit::log('Sales', 'RECEIPT_VIEW',
                'Viewed receipt ' . $sale['invoice_no'] . ' for oversight');
        }

        view_bare('pos/receipt', [
            'sale'  => $sale,
            'items' => Sale::items($id),
            'units' => ProductUnit::forSale($id),
            // True when the office is looking rather than the till printing.
            'copy'  => !$mine,
        ], 'Receipt ' . $sale['invoice_no']);
    }
}
