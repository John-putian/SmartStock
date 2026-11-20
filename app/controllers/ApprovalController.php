<?php
/**
 * FR-01 / FR-04b / FR-06 - the approvals desk.
 *
 * Two queues, one screen, because they are the same decision wearing two hats:
 * somebody on the floor wants to charge less than the listed price, and is not
 * allowed to decide that alone. A price request moves the shelf price; a
 * discount request takes a percentage off one sale that is waiting at the till.
 *
 * Two audiences share it. An administrator or manager sees every request and
 * the buttons to rule on it; everyone else sees only the ones they raised,
 * read-only, so they can tell whether the mark-down has been allowed.
 */
class ApprovalController
{
    public static function index(): void
    {
        $canDecide = Auth::canApprove();
        $status    = (string) input('status', $canDecide ? 'pending' : '');
        if (!in_array($status, ['', 'pending', 'approved', 'rejected'], true)) {
            $status = '';
        }

        $mine = $canDecide ? null : Auth::id();

        $discounts = DiscountRequest::listing($status, $mine);

        view('approvals/index', [
            'requests'  => PriceRequest::listing($status, $mine),
            'discounts' => $discounts,
            'status'    => $status,
            'canDecide' => $canDecide,
            'canDecideDiscount' => Auth::canApproveDiscount(),
            'counts'    => [
                'pending'  => ($canDecide ? PriceRequest::pendingCount()
                                          : PriceRequest::pendingCountFor((int) Auth::id()))
                            + ($canDecide ? DiscountRequest::pendingCount()
                                          : DiscountRequest::pendingCountFor((int) Auth::id())),
                'approved' => count(PriceRequest::listing('approved', $mine, 500))
                            + count(DiscountRequest::listing('approved', $mine, 500)),
                'rejected' => count(PriceRequest::listing('rejected', $mine, 500))
                            + count(DiscountRequest::listing('rejected', $mine, 500)),
            ],
        ], $canDecide ? 'Price Approvals' : 'My Requests');
    }

    public static function decide(): void
    {
        verify_csrf();
        $id      = (int) input('id');
        $approve = input('decision') === 'approve';
        $note    = trim((string) input('note', ''));

        $result = PriceRequest::decide($id, $approve, $note);

        if (!$result['ok']) {
            flash('danger', '<strong>Nothing was changed.</strong> ' . e($result['error']));
        } elseif ($approve) {
            $r = $result['request'];
            flash('success', 'Approved. <strong>' . e($r['product_name'])
                . '</strong> is now listed at ' . peso($r['new_price']) . '.');
        } else {
            $r = $result['request'];
            flash('info', 'Rejected. <strong>' . e($r['product_name'])
                . '</strong> keeps its price of ' . peso($r['old_price']) . '.');
        }
        redirect('approvals', array_filter(['status' => input('status', '')]));
    }

    /**
     * FR-04b - rule on a discount the till is waiting for.
     *
     * Approving does not give the money away; it lets the till give it away
     * once, on the sale it is holding. The approval is spent at checkout.
     */
    public static function decideDiscount(): void
    {
        verify_csrf();
        $id      = (int) input('id');
        $approve = input('decision') === 'approve';
        $note    = trim((string) input('note', ''));

        $result = DiscountRequest::decide($id, $approve, $note);

        if (!$result['ok']) {
            flash('danger', '<strong>Nothing was changed.</strong> ' . e($result['error']));
        } else {
            $r   = $result['request'];
            $pct = DiscountRequest::pct((float) $r['percent']);
            $who = e((string) ($r['asked_by_name'] ?? 'the till'));
            flash($approve ? 'success' : 'info', $approve
                ? 'Approved. <strong>' . $who . '</strong> may take ' . e($pct)
                  . ' off that sale. It can only be used once.'
                : 'Rejected. <strong>' . $who . '</strong> has been told the sale '
                  . 'stands at full price.');
        }
        redirect('approvals', array_filter(['status' => input('status', '')]));
    }
}
