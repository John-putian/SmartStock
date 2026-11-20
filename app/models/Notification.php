<?php
/**
 * FR-11 - Notifications.
 *
 * The audit trail answers "what happened?". This answers "what do I need to do
 * about it?", and it answers it to a named person rather than to the room.
 *
 * Two rules hold everywhere in this class:
 *
 *   1. Raising a notice must never break the thing that raised it. A sale that
 *      succeeds must not be rolled back because a notice could not be written,
 *      so every write is wrapped and failures are swallowed deliberately.
 *   2. A repeated event updates its own notice instead of stacking. An item
 *      that dips below its reorder level twice in a day is one notice, not two,
 *      which is what `dedupe_key` is for.
 */
class Notification
{
    /** The office: the people who decide things. */
    public const OFFICE = ['admin', 'manager'];

    /** Human labels and icons for each type, used by the views. */
    public const TYPES = [
        'stock_low'      => ['Stock running low',  'exclamation-triangle'],
        'stock_out'      => ['Sold out',           'x-octagon'],
        'price_request'  => ['Price request',      'tag'],
        'price_decision' => ['Price decision',     'patch-check'],
        'discount_request'  => ['Discount request',   'percent'],
        'discount_decision' => ['Discount decision',  'patch-check'],
        'discount'          => ['Discount given',     'percent'],
        'daily_income'   => ['Daily income',       'cash-coin'],
    ];

    /**
     * A peso amount for storing inside a notice.
     *
     * peso() returns the HTML entity &#8369;, which is right for a template but
     * wrong here: a notice is stored as plain text and escaped again on the way
     * out, so the entity would show up literally. The real sign, being UTF-8,
     * passes through htmlspecialchars() untouched.
     */
    private static function money($v): string
    {
        return "\u{20B1}" . number_format((float) $v, 2);
    }

    /**
     * Write one notice for one account. Returns true when something was stored.
     *
     * @param array $n  type, severity, title, body, page, params, dedupe
     */
    public static function to(int $userId, array $n): bool
    {
        if ($userId <= 0) {
            return false;
        }
        try {
            Database::run(
                'INSERT INTO notifications
                    (user_id, type, severity, title, body, link_page, link_params, dedupe_key)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    severity   = VALUES(severity),
                    title      = VALUES(title),
                    body       = VALUES(body),
                    read_at    = NULL,
                    created_at = NOW()',
                [
                    $userId,
                    $n['type'],
                    $n['severity'] ?? 'info',
                    mb_substr((string) $n['title'], 0, 140),
                    isset($n['body']) ? mb_substr((string) $n['body'], 0, 250) : null,
                    $n['page']   ?? null,
                    isset($n['params']) ? http_build_query($n['params']) : null,
                    $n['dedupe'] ?? null,
                ]);
            return true;
        } catch (Throwable $e) {
            // Deliberately swallowed: a notice is never worth failing a sale for.
            return false;
        }
    }

    /** Write the same notice to every active account holding one of $roles. */
    public static function toRoles(array $roles, array $n): int
    {
        try {
            $in   = implode(',', array_fill(0, count($roles), '?'));
            $rows = Database::all(
                "SELECT id FROM users WHERE status = 'active' AND role IN ($in)", $roles);
        } catch (Throwable $e) {
            return 0;
        }
        $sent = 0;
        foreach ($rows as $r) {
            if (self::to((int) $r['id'], $n)) {
                $sent++;
            }
        }
        return $sent;
    }

    /** The office: administrators and store managers. */
    public static function toOffice(array $n): int
    {
        return self::toRoles(self::OFFICE, $n);
    }

    // ------------------------------------------------------------ reading

    public static function unreadCount(int $userId): int
    {
        try {
            return (int) Database::value(
                'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
                [$userId]);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Latest notices for the bell dropdown or the full page. */
    public static function forUser(int $userId, array $f = []): array
    {
        $sql  = 'SELECT * FROM notifications WHERE user_id = ?';
        $args = [$userId];

        if (($f['status'] ?? '') === 'unread') {
            $sql .= ' AND read_at IS NULL';
        } elseif (($f['status'] ?? '') === 'read') {
            $sql .= ' AND read_at IS NOT NULL';
        }
        if (!empty($f['type']) && isset(self::TYPES[$f['type']])) {
            $sql .= ' AND type = ?';
            $args[] = $f['type'];
        }
        $sql .= ' ORDER BY read_at IS NOT NULL, created_at DESC, id DESC LIMIT '
              . max(1, min(200, (int) ($f['limit'] ?? 50)));

        try {
            return Database::all($sql, $args);
        } catch (Throwable $e) {
            return [];
        }
    }

    /** How many of each type are unread, for the filter pills. */
    public static function countsByType(int $userId): array
    {
        try {
            $rows = Database::all(
                'SELECT type, COUNT(*) n FROM notifications
                 WHERE user_id = ? AND read_at IS NULL GROUP BY type', [$userId]);
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[$r['type']] = (int) $r['n'];
        }
        return $out;
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::run(
            'UPDATE notifications SET read_at = NOW()
             WHERE id = ? AND user_id = ? AND read_at IS NULL', [$id, $userId]);
    }

    public static function markAllRead(int $userId): int
    {
        $st = Database::run(
            'UPDATE notifications SET read_at = NOW()
             WHERE user_id = ? AND read_at IS NULL', [$userId]);
        return $st->rowCount();
    }

    // ------------------------------------------------------------ triggers

    /**
     * Called after any movement that changes a quantity. Raises a notice only
     * when the item has actually crossed a line - sold out, or fallen to the
     * reorder level - so restocking a healthy item stays quiet.
     */
    public static function stockChanged(int $productId): void
    {
        try {
            $p = Database::one(
                'SELECT p.id, p.sku, p.name, p.quantity, p.reorder_level, c.name AS category
                 FROM products p LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.id = ? AND p.status = "active"', [$productId]);
        } catch (Throwable $e) {
            return;
        }
        if (!$p) {
            return;
        }

        $qty     = (int) $p['quantity'];
        $reorder = (int) $p['reorder_level'];
        $where   = $p['category'] ? ' (' . $p['category'] . ')' : '';

        if ($qty <= 0) {
            self::toOffice([
                'type' => 'stock_out', 'severity' => 'danger',
                'title' => 'Sold out: ' . $p['name'],
                'body'  => $p['sku'] . $where . ' has no pieces left on the floor. '
                         . 'Worth raising at the next container meeting.',
                'page'  => 'inventory.view', 'params' => ['id' => $p['id']],
                'dedupe' => 'stock_out:' . $p['id'],
            ]);
        } elseif ($qty <= $reorder) {
            self::toOffice([
                'type' => 'stock_low', 'severity' => 'warning',
                'title' => 'Running low: ' . $p['name'],
                'body'  => $p['sku'] . $where . ' is down to ' . $qty . ' piece(s), '
                         . 'at or below its reorder level of ' . $reorder . '.',
                'page'  => 'inventory.view', 'params' => ['id' => $p['id']],
                'dedupe' => 'stock_low:' . $p['id'],
            ]);
        } else {
            // Back in healthy stock: clear the standing warnings for this item.
            try {
                Database::run(
                    'DELETE FROM notifications
                     WHERE dedupe_key IN (?, ?)',
                    ['stock_low:' . $p['id'], 'stock_out:' . $p['id']]);
            } catch (Throwable $e) {
                // Nothing to do; a stale warning is harmless.
            }
        }
    }

    /** A price change is waiting on a decision. */
    public static function priceRequested(int $requestId, array $product,
                                          float $old, float $new, string $asker): void
    {
        $dir = $new < $old ? 'lower' : 'raise';
        self::toOffice([
            'type' => 'price_request', 'severity' => 'warning',
            'title' => 'Price approval needed: ' . ($product['name'] ?? 'an item'),
            'body'  => $asker . ' asked to ' . $dir . ' the price from '
                     . self::money($old) . ' to ' . self::money($new)
                     . '. The listed price has not moved.',
            'page'  => 'approvals', 'params' => ['status' => 'pending'],
            'dedupe' => 'price_request:' . $requestId,
        ]);
    }

    /** The person who asked is told what was decided. */
    public static function priceDecided(int $requesterId, array $request,
                                        bool $approved, string $decider): void
    {
        self::to($requesterId, [
            'type' => 'price_decision',
            'severity' => $approved ? 'success' : 'info',
            'title' => ($approved ? 'Approved: ' : 'Not approved: ')
                     . ($request['product_name'] ?? 'your price request'),
            'body'  => $approved
                ? $decider . ' approved the change. The item is now listed at '
                  . self::money($request['new_price']) . '.'
                : $decider . ' kept the price at ' . self::money($request['old_price'])
                  . '.' . (!empty($request['decision_note']) ? ' "' . $request['decision_note'] . '"' : ''),
            'page'  => 'approvals',
            'dedupe' => 'price_decision:' . ($request['id'] ?? 0),
        ]);
    }

    /**
     * The till is asking for money off and cannot grant it itself. This is
     * the notice that puts the decision in front of the office.
     */
    public static function discountRequested(int $requestId, float $percent,
                                             float $cartTotal, string $asker,
                                             string $role): void
    {
        self::toRoles(['manager'], [
            'type' => 'discount_request', 'severity' => 'warning',
            'title' => DiscountRequest::pct($percent) . ' discount requested by ' . $asker,
            'body'  => 'On a cart of ' . self::money($cartTotal) . ', which would take '
                     . self::money($cartTotal * $percent / 100) . ' off. The sale is '
                     . 'held at the till until somebody rules on it.',
            'page'  => 'approvals', 'params' => ['status' => 'pending'],
            'dedupe' => 'discount_request:' . $requestId,
        ]);
    }

    /** The till is told whether it may give the money away. */
    public static function discountDecided(int $requesterId, array $request,
                                           bool $approved, string $decider,
                                           string $note = ''): void
    {
        $pct = DiscountRequest::pct((float) $request['percent']);
        self::to($requesterId, [
            'type' => 'discount_decision',
            'severity' => $approved ? 'success' : 'info',
            'title' => ($approved ? 'Approved: ' : 'Not approved: ') . $pct . ' discount',
            'body'  => $approved
                ? $decider . ' approved it. Go back to the till and complete the sale.'
                : $decider . ' turned it down. The sale stands at full price.'
                  . ($note !== '' ? ' "' . $note . '"' : ''),
            'page'  => $approved ? 'pos' : 'approvals',
            'dedupe' => 'discount_decision:' . ($request['id'] ?? 0),
        ]);
    }

    /** Money came off a sale. The office is told who allowed it. */
    public static function discountGiven(string $invoice, float $amount,
                                         string $cashier, string $approver,
                                         float $percent = 0): void
    {
        self::toOffice([
            'type' => 'discount', 'severity' => 'info',
            'title' => ($percent > 0 ? DiscountRequest::pct($percent) . ' (' . self::money($amount) . ')'
                                     : self::money($amount)) . ' off ' . $invoice,
            'body'  => 'Rung up by ' . $cashier . ', authorised by ' . $approver . '.',
            'page'  => 'reports.sales',
            'dedupe' => 'discount:' . $invoice,
        ]);
    }

    /**
     * End-of-day takings for the office.
     *
     * There is no scheduler on a XAMPP install, so the digest is produced
     * lazily: the first time anybody opens a page on a new day, yesterday's
     * summary is written if it is missing and there were sales to report. The
     * dedupe key is the date, so it can only ever be written once.
     */
    public static function dailyIncomeDigest(): void
    {
        $day = date('Y-m-d', strtotime('-1 day'));

        try {
            $already = Database::value(
                'SELECT COUNT(*) FROM notifications WHERE dedupe_key = ?',
                ['daily_income:' . $day]);
            if ($already) {
                return;
            }

            $s = Database::one(
                'SELECT COUNT(*) txn, COALESCE(SUM(total),0) revenue,
                        COALESCE(SUM(discount),0) discounts
                 FROM sales WHERE DATE(sale_date) = ?', [$day]);
            if (!$s || (int) $s['txn'] === 0) {
                return;                       // a closed day needs no digest
            }

            $pieces = (int) Database::value(
                'SELECT COALESCE(SUM(i.quantity),0)
                 FROM sale_items i JOIN sales s ON s.id = i.sale_id
                 WHERE DATE(s.sale_date) = ?', [$day]);

            $best = Database::one(
                'SELECT COALESCE(c.name,"Uncategorised") category,
                        SUM(i.line_total) revenue
                 FROM sale_items i
                 JOIN sales s           ON s.id = i.sale_id
                 LEFT JOIN products p   ON p.id = i.product_id
                 LEFT JOIN categories c ON c.id = p.category_id
                 WHERE DATE(s.sale_date) = ?
                 GROUP BY category ORDER BY revenue DESC LIMIT 1', [$day]);

            $body = num($s['txn']) . ' sale(s), ' . num($pieces) . ' piece(s).';
            if ($best) {
                $body .= ' Best category: ' . $best['category']
                       . ' at ' . self::money($best['revenue']) . '.';
            }
            if ((float) $s['discounts'] > 0) {
                $body .= ' Discounts given: ' . self::money($s['discounts']) . '.';
            }

            self::toOffice([
                'type' => 'daily_income', 'severity' => 'success',
                'title' => 'Takings for ' . date('l, F j', strtotime($day))
                         . ': ' . self::money($s['revenue']),
                'body'  => $body,
                'page'  => 'reports.category',
                'dedupe' => 'daily_income:' . $day,
            ]);
        } catch (Throwable $e) {
            // The digest is a convenience; never let it interrupt a page load.
            return;
        }

        // FR-11b - the same figures by email, to the office accounts that
        // carry an address. Outside the try above on purpose: the notice is
        // already safely written, and a mail server that is slow or wrong
        // must not look like a failure of the digest.
        try {
            Mailer::sendNotice('daily_income',
                'Takings for ' . date('l, j F Y', strtotime($day))
                . ' - ' . self::money($s['revenue']),
                self::digestData($day, $s, $pieces, $best));
        } catch (Throwable $e) {
            // A notice that reached the bell but not an inbox is still a notice.
        }
    }

    /**
     * The figures the takings email is built from. Shared with the preview
     * on the mail settings screen, so what is previewed is what is sent.
     */
    public static function digestData(string $day, array $s, int $pieces,
                                      ?array $best): array
    {
        return [
            'day'          => date('l, j F Y', strtotime($day)),
            'revenue'      => (float) $s['revenue'],
            'txn'          => (int) $s['txn'],
            'pieces'       => $pieces,
            'bestCategory' => $best['category'] ?? '',
            'bestRevenue'  => (float) ($best['revenue'] ?? 0),
            'discounts'    => (float) $s['discounts'],
            'link'         => base_url('index.php?page=reports.category'),
            'preview'      => false,
        ];
    }

    /**
     * Yesterday's figures, worked out on demand. Used by the settings screen
     * to preview and to send a test without waiting for a new day; falls back
     * to the most recent day that had any sales, so a preview is never blank.
     */
    public static function latestDigestData(): array
    {
        $day = (string) Database::value(
            'SELECT DATE(sale_date) FROM sales
             WHERE DATE(sale_date) <= CURDATE()
             ORDER BY sale_date DESC LIMIT 1');
        if ($day === '') {
            return [];
        }

        $s = Database::one(
            'SELECT COUNT(*) txn, COALESCE(SUM(total),0) revenue,
                    COALESCE(SUM(discount),0) discounts
             FROM sales WHERE DATE(sale_date) = ?', [$day]);
        $pieces = (int) Database::value(
            'SELECT COALESCE(SUM(i.quantity),0)
             FROM sale_items i JOIN sales s ON s.id = i.sale_id
             WHERE DATE(s.sale_date) = ?', [$day]);
        $best = Database::one(
            'SELECT COALESCE(c.name,"Uncategorised") category,
                    SUM(i.line_total) revenue
             FROM sale_items i
             JOIN sales s           ON s.id = i.sale_id
             LEFT JOIN products p   ON p.id = i.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE DATE(s.sale_date) = ?
             GROUP BY category ORDER BY revenue DESC LIMIT 1', [$day]);

        return self::digestData($day, (array) $s, $pieces, $best);
    }
}
