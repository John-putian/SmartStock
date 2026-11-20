<?php
/**
 * FR-01 / FR-06 - price reduction requests.
 *
 * Work staff price the goods they unpack, and that is deliberate: they are the
 * ones holding the piece. What they may not do is quietly mark something down.
 * A reduction is therefore written here and the item keeps its old price until
 * an administrator or manager rules on it.
 */
class PriceRequest
{
    /**
     * Record a reduction that the requester was not allowed to apply.
     * Returns the new request id.
     */
    public static function open(int $productId, float $oldPrice, float $newPrice,
                                string $reason = ''): int
    {
        Database::run(
            'INSERT INTO price_requests (product_id, requested_by, old_price, new_price, reason)
             VALUES (?,?,?,?,?)',
            [$productId, Auth::id(), round($oldPrice, 2), round($newPrice, 2),
             mb_substr(trim($reason), 0, 250) ?: null]);

        $id = Database::lastId();
        $p  = Product::find($productId);
        Audit::log('Price Control', 'REQUEST',
            'Asked to ' . ($newPrice < $oldPrice ? 'lower' : 'raise') . ' ' .
            ($p['sku'] ?? $productId) . ' from ' .
            number_format($oldPrice, 2) . ' to ' . number_format($newPrice, 2));

        // Tell the office something is waiting on them.
        Notification::priceRequested($id, $p ?? [], $oldPrice, $newPrice,
            Auth::user()['full_name'] ?? 'A staff member');
        return $id;
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT r.*, p.sku, p.name AS product_name, p.selling_price AS current_price,
                    p.image_path, c.name AS category_name,
                    a.full_name AS asked_by_name, a.username AS asked_by_user, a.role AS asked_by_role,
                    d.full_name AS decided_by_name, d.role AS decided_by_role
             FROM price_requests r
             JOIN products p        ON p.id = r.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users a      ON a.id = r.requested_by
             LEFT JOIN users d      ON d.id = r.decided_by
             WHERE r.id = ?', [$id]);
    }

    /**
     * Requests for the approvals screen. $mine limits the list to one account,
     * which is what a work staff member sees - their own asks, nobody else's.
     */
    public static function listing(string $status = '', ?int $mine = null, int $limit = 200): array
    {
        $sql = 'SELECT r.*, p.sku, p.name AS product_name, p.selling_price AS current_price,
                       p.image_path, c.name AS category_name,
                       a.full_name AS asked_by_name, a.username AS asked_by_user, a.role AS asked_by_role,
                       d.full_name AS decided_by_name, d.role AS decided_by_role
                FROM price_requests r
                JOIN products p        ON p.id = r.product_id
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN users a      ON a.id = r.requested_by
                LEFT JOIN users d      ON d.id = r.decided_by
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
        // Waiting decisions float to the top, newest first within each group.
        $sql .= " ORDER BY FIELD(r.status,'pending','rejected','approved'), r.created_at DESC
                  LIMIT " . max(1, min(500, $limit));

        return Database::all($sql, $args);
    }

    public static function pendingCount(): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM price_requests WHERE status = "pending"');
    }

    /** How many of one person's requests are still waiting. */
    public static function pendingCountFor(int $userId): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM price_requests WHERE status = "pending" AND requested_by = ?',
            [$userId]);
    }

    /** Is this item already waiting on a decision? Avoids duplicate asks. */
    public static function pendingForProduct(int $productId): ?array
    {
        return Database::one(
            'SELECT * FROM price_requests
             WHERE product_id = ? AND status = "pending"
             ORDER BY created_at DESC LIMIT 1', [$productId]);
    }

    /**
     * Approve or reject. Approving is the only path by which a reduction
     * actually reaches the products table.
     *
     * @return array{ok:bool,error?:string,request?:array}
     */
    public static function decide(int $id, bool $approve, string $note = ''): array
    {
        if (!Auth::canApprove()) {
            return ['ok' => false, 'error' => 'Only an administrator or manager may decide this.'];
        }

        $r = self::find($id);
        if (!$r) {
            return ['ok' => false, 'error' => 'That request could not be found.'];
        }
        if ($r['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'That request has already been ' . $r['status'] . '.'];
        }

        Database::begin();
        try {
            $locked = Database::one('SELECT * FROM price_requests WHERE id = ? FOR UPDATE', [$id]);
            if (!$locked || $locked['status'] !== 'pending') throw new RuntimeException('Already decided.');
            $product = Database::one('SELECT * FROM products WHERE id = ? FOR UPDATE', [$r['product_id']]);
            if ($approve && (!$product || $product['status'] !== 'active' || abs((float) $product['selling_price'] - (float) $r['old_price']) >= 0.01)) {
                throw new RuntimeException('The listed price changed. Reject this request and ask for a new one.');
            }
            Database::run(
                'UPDATE price_requests
                 SET status = ?, decided_by = ?, decision_note = ?, decided_at = NOW()
                 WHERE id = ? AND status = "pending"',
                [$approve ? 'approved' : 'rejected', Auth::id(),
                 mb_substr(trim($note), 0, 250) ?: null, $id]);

            if ($approve) {
                Product::setPrice((int) $r['product_id'], (float) $r['new_price'],
                    'Price reduction request #' . $id . ' approved');
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollback();
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        Audit::log('Price Control', $approve ? 'APPROVE' : 'REJECT',
            ($approve ? 'Approved' : 'Rejected') . ' price change on ' . $r['sku'] .
            ' (' . number_format((float) $r['old_price'], 2) . ' to ' .
            number_format((float) $r['new_price'], 2) . ')');

        // Close the loop: the person who asked is told the outcome.
        if (!empty($r['requested_by'])) {
            Notification::priceDecided((int) $r['requested_by'],
                $r + ['decision_note' => trim($note)], $approve,
                Auth::user()['full_name'] ?? 'The office');
        }

        return ['ok' => true, 'request' => $r];
    }
}
