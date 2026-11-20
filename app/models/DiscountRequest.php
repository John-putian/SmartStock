<?php
/**
 * FR-04b - discount requests.
 *
 * A discount is money off the owner's goods, so it is not the cashier's to
 * give. The till can ask for one; it cannot grant one. The request is written
 * here, the office is told, and the cart stays at full price until an
 * Store Manager rules on it.
 *
 * The discount is a PERCENTAGE, not a peso figure. A percentage survives the
 * cart changing - add another chair and "10% off" still means what it said,
 * where "500 off" quietly becomes a different bargain. The peso figure is
 * worked out from the percentage at checkout and stored alongside it.
 */
class DiscountRequest
{
    public const MAX_PERCENT = 100.0;

    /**
     * File a request against the cart as it stands. Returns the new id.
     * The cart total is a snapshot for the approver's benefit; the discount
     * itself is recomputed from the percentage when the sale is rung up.
     */
    public static function open(float $percent, float $cartTotal, string $reason = '',
                                string $fingerprint = ''): int
    {
        $percent = self::clamp($percent);

        Database::run(
            'INSERT INTO discount_requests
                (requested_by, percent, cart_total, cart_fingerprint, reason, expires_at)
             VALUES (?,?,?,?,?, DATE_ADD(NOW(), INTERVAL 8 HOUR))',
            [Auth::id(), $percent, round($cartTotal, 2), $fingerprint,
             mb_substr(trim($reason), 0, 250) ?: null]);

        $id = Database::lastId();

        Audit::log('Sales', 'DISCOUNT_REQUEST',
            'Asked for ' . self::pct($percent) . ' off a cart of '
            . number_format($cartTotal, 2));

        Notification::discountRequested($id, $percent, $cartTotal,
            Auth::user()['full_name'] ?? 'the till',
            (string) (Auth::user()['role'] ?? 'cashier'));

        return $id;
    }

    /**
     * A fingerprint of the exact sale an approval was granted against.
     *
     * An approval used to belong to the CASHIER, so whatever was in the cart
     * at checkout received the discount - a 10% sign-off on an 850 peso cart
     * could be spent on a 50,000 peso one. The hash covers every piece in the
     * cart and the price each one carries, so adding, removing or repricing
     * anything changes it and the approval stops matching.
     *
     * @param int[] $cart product_unit ids
     */
    public static function fingerprint(array $cart): string
    {
        $parts = [];
        foreach (array_unique(array_map('intval', $cart)) as $unitId) {
            $r = Database::one(
                'SELECT u.id, p.selling_price
                 FROM product_units u JOIN products p ON p.id = u.product_id
                 WHERE u.id = ?', [$unitId]);
            if ($r) {
                $parts[] = $r['id'] . ':' . number_format((float) $r['selling_price'], 2, '.', '');
            }
        }
        sort($parts);
        return hash('sha256', implode('|', $parts));
    }

    /** 0 to 100, to two places. Anything outside is pulled back inside. */
    public static function clamp(float $percent): float
    {
        return round(max(0.0, min(self::MAX_PERCENT, $percent)), 2);
    }

    /** "12.5%" rather than "12.50%", which reads like money. */
    public static function pct(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.') . '%';
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT r.*,
                    a.full_name AS asked_by_name, a.username AS asked_by_user,
                    a.role AS asked_by_role,
                    d.full_name AS decided_by_name, d.role AS decided_by_role
             FROM discount_requests r
             LEFT JOIN users a ON a.id = r.requested_by
             LEFT JOIN users d ON d.id = r.decided_by
             WHERE r.id = ?', [$id]);
    }

    /**
     * The request this account is currently waiting on, if any. A till may
     * have only one in flight - asking twice for the same cart is noise.
     */
    public static function pendingFor(int $userId): ?array
    {
        return Database::one(
            'SELECT * FROM discount_requests
             WHERE requested_by = ? AND status = "pending"
             ORDER BY id DESC LIMIT 1', [$userId]);
    }

    /**
     * An approved request this account has not yet spent. Returned so the
     * till can show "10% approved by Carl" and apply it at checkout.
     */
    public static function approvedFor(int $userId, ?array $cart = null): ?array
    {
        $r = Database::one(
            'SELECT r.*, d.full_name AS decided_by_name
             FROM discount_requests r
             LEFT JOIN users d ON d.id = r.decided_by
             WHERE r.requested_by = ? AND r.status = "approved" AND r.sale_id IS NULL
             ORDER BY r.id DESC LIMIT 1', [$userId]);

        if (!$r) return null;

        // Time-limited, so an approval cannot be banked and spent days later.
        if (!empty($r['expires_at']) && strtotime($r['expires_at']) < time()) {
            Database::run('UPDATE discount_requests SET status = "expired" WHERE id = ?', [$r['id']]);
            return null;
        }

        // Granted against a particular sale. Hand it back only while the cart
        // is still that sale; the caller shows "the sale changed" otherwise.
        if ($cart !== null && !empty($r['cart_fingerprint'])) {
            $r['stale'] = ($r['cart_fingerprint'] !== self::fingerprint($cart));
        } else {
            $r['stale'] = false;
        }
        $senior = Database::value('SELECT COUNT(*) FROM users WHERE id = ? AND status = "active" AND role IN ("admin","manager")', [$r['decided_by']]);
        if (!$senior) {
            $r['stale'] = true;
            $r['stale_reason'] = 'The account that approved this is no longer an active administrator or Store Manager. Cancel it and ask again.';
        }
        return $r;
    }

    /** Whatever the till should currently be showing: pending, or approved. */
    public static function liveFor(int $userId): ?array
    {
        return self::pendingFor($userId) ?? self::approvedFor($userId);
    }

    public static function listing(string $status = '', ?int $mine = null, int $limit = 200): array
    {
        $sql = 'SELECT r.*,
                       a.full_name AS asked_by_name, a.username AS asked_by_user,
                       a.role AS asked_by_role,
                       d.full_name AS decided_by_name, d.role AS decided_by_role,
                       s.invoice_no
                FROM discount_requests r
                LEFT JOIN users a ON a.id = r.requested_by
                LEFT JOIN users d ON d.id = r.decided_by
                LEFT JOIN sales s ON s.id = r.sale_id
                WHERE 1 = 1';
        $args = [];

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $sql .= ' AND r.status = ?';
            $args[] = $status;
        }
        if ($mine !== null) {
            $sql .= ' AND r.requested_by = ?';
            $args[] = $mine;
        }
        $sql .= " ORDER BY FIELD(r.status,'pending','approved','rejected'), r.created_at DESC
                  LIMIT " . max(1, min(500, $limit));

        return Database::all($sql, $args);
    }

    public static function pendingCount(): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM discount_requests WHERE status = "pending"');
    }

    public static function pendingCountFor(int $userId): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM discount_requests
             WHERE status = "pending" AND requested_by = ?', [$userId]);
    }

    /**
     * Approve or reject. Approving does not spend the discount - it only
     * makes it available to the till, which applies it at checkout.
     *
     * @return array{ok:bool,error?:string,request?:array}
     */
    public static function decide(int $id, bool $approve, string $note = ''): array
    {
        if (!Auth::canApproveDiscount()) {
            return ['ok' => false, 'error' => 'Only the administrator or a Store Manager may decide discount requests.'];
        }

        $r = self::find($id);
        if (!$r) {
            return ['ok' => false, 'error' => 'That request could not be found.'];
        }
        if ($r['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'That request has already been ' . $r['status'] . '.'];
        }

        try {
            $decision = Database::run(
                'UPDATE discount_requests
                 SET status = ?, decided_by = ?, decision_note = ?, decided_at = NOW()
                 WHERE id = ? AND status = "pending"',
                [$approve ? 'approved' : 'rejected', Auth::id(),
                 mb_substr(trim($note), 0, 250) ?: null, $id]);
            if ($decision->rowCount() !== 1) return ['ok'=>false, 'error'=>'That request was already decided.'];
            if ($approve) Database::run('UPDATE discount_requests SET expires_at = DATE_ADD(NOW(), INTERVAL 2 HOUR) WHERE id = ? AND expires_at IS NULL', [$id]);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'The decision could not be saved.'];
        }

        Audit::log('Sales', $approve ? 'DISCOUNT_APPROVE' : 'DISCOUNT_REJECT',
            ($approve ? 'Approved' : 'Rejected') . ' ' . self::pct((float) $r['percent'])
            . ' for ' . ($r['asked_by_name'] ?? 'the till'));

        if (!empty($r['requested_by'])) {
            Notification::discountDecided((int) $r['requested_by'], $r, $approve,
                Auth::user()['full_name'] ?? 'The office', trim($note));
        }

        return ['ok' => true, 'request' => $r];
    }

    /** Mark the approval as spent on a sale, so it cannot be reused. */
    public static function attachToSale(int $id, int $saleId): void
    {
        Database::run(
            'UPDATE discount_requests SET sale_id = ?
             WHERE id = ? AND sale_id IS NULL', [$saleId, $id]);
    }

    /** The till withdrawing its own ask. */
    public static function cancel(int $id, int $userId): bool
    {
        // Keep the decision trail: a withdrawn ask becomes 'cancelled'
        // rather than vanishing from the record.
        $st = Database::run(
            'UPDATE discount_requests SET status = "cancelled"
             WHERE id = ? AND requested_by = ? AND status = "pending"',
            [$id, $userId]);
        return $st->rowCount() > 0;
    }
}
