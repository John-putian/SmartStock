<?php
/** FR-04, FR-05, FR-07 - Point of sale transactions and sales reporting. */
class Sale
{
    /**
     * Record a completed sale inside one transaction:
     * header + line items + the individual pieces sold + ledger + audit log.
     *
     * The cart holds the ids of specific physical units, because every
     * second-hand piece is distinct — selling "a chair" means selling
     * chair AA-FUR-0007-03, not one of an interchangeable five.
     *
     * @param array $cart  unit ids
     */
    public static function checkout(array $cart, array $meta): array
    {
        if (!$cart) {
            return ['ok' => false, 'error' => 'The cart is empty.'];
        }

        // One submission, one sale. If the cashier double-clicks, the second
        // request carries the same key, hits the unique index and is answered
        // with the sale the first one already created instead of an alarming
        // "that piece has already been sold".
        $idemKey = (string) ($meta['idempotency_key'] ?? '');
        if ($idemKey !== '') {
            $existing = Database::one(
                'SELECT id, invoice_no FROM sales WHERE idempotency_key = ?', [$idemKey]);
            if ($existing) {
                return ['ok' => true, 'sale_id' => (int) $existing['id'],
                        'invoice' => $existing['invoice_no'], 'duplicate' => true];
            }
        }

        Database::begin();
        try {
            $ids = array_values(array_unique(array_map('intval', $cart)));
            sort($ids);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $parents = Database::all('SELECT DISTINCT product_id FROM product_units WHERE id IN (' . $marks . ') ORDER BY product_id', $ids);
            foreach ($parents as $parent) {
                Database::one('SELECT id FROM products WHERE id = ? FOR UPDATE', [$parent['product_id']]);
            }
            if ($idemKey !== '') {
                $existing = Database::one('SELECT id, invoice_no FROM sales WHERE idempotency_key = ? FOR UPDATE', [$idemKey]);
                if ($existing) {
                    Database::commit();
                    return ['ok'=>true, 'sale_id'=>(int)$existing['id'], 'invoice'=>$existing['invoice_no'], 'duplicate'=>true];
                }
            }
            // Lock each piece and group them under their item.
            $grouped = [];
            foreach ($ids as $unitId) {
                $u = Database::one(
                    'SELECT u.*, p.name, p.sku, p.selling_price, p.cost_price,
                            p.status AS product_status, c.name AS category_name
                     FROM product_units u
                     JOIN products p        ON p.id = u.product_id
                     LEFT JOIN categories c ON c.id = p.category_id
                     WHERE u.id = ? FOR UPDATE', [$unitId]);

                if (!$u || $u['product_status'] !== 'active') {
                    throw new RuntimeException('An item in the cart is no longer available.');
                }
                if ($u['status'] !== 'in_stock') {
                    throw new RuntimeException(
                        'Piece ' . $u['unit_code'] . ' (' . $u['name'] . ') has already been sold.');
                }
                $grouped[(int) $u['product_id']]['product'] = $u;
                $grouped[(int) $u['product_id']]['units'][] = $u;
            }

            $lines    = [];
            $subtotal = 0.0;
            foreach ($grouped as $pid => $g) {
                $qty       = count($g['units']);
                $price     = (float) $g['product']['selling_price'];
                $line      = $qty * $price;
                $subtotal += $line;
                $lines[]   = [
                    'p'     => ['id' => $pid, 'name' => $g['product']['name'],
                                'sku' => $g['product']['sku'], 'selling_price' => $price,
                                'cost' => $g['product']['cost_price'],
                                'category' => $g['product']['category_name']],
                    'qty'   => $qty,
                    'line'  => $line,
                    'units' => $g['units'],
                ];
            }

            // ---------------------------------------------------------------
            // FR-04b : the discount approval is validated, locked and SPENT
            // inside this transaction.
            //
            // It used to be marked used after the commit, so two checkouts
            // racing each other both read the same approval and both sales
            // got the discount. Locking the row here means the second one
            // waits, then finds it already used and is refused.
            //
            // The approval is also bound to THESE pieces at THESE prices. If
            // the cart changed after it was granted, the fingerprint no
            // longer matches and the approval does not apply.
            // ---------------------------------------------------------------
            $percent    = 0.0;
            $approvedBy = null;
            $approval   = null;
            $reqId      = (int) ($meta['discount_request_id'] ?? 0);

            if ($reqId > 0) {
                $approval = Database::one(
                    'SELECT * FROM discount_requests WHERE id = ? FOR UPDATE', [$reqId]);

                if (!$approval) {
                    throw new RuntimeException('That discount approval could not be found.');
                }
                if ($approval['status'] === 'used' || $approval['sale_id'] !== null) {
                    throw new RuntimeException(
                        'That discount has already been used on another sale.');
                }
                if ($approval['status'] !== 'approved') {
                    throw new RuntimeException(
                        'That discount is ' . $approval['status'] . ', not approved.');
                }
                if (!empty($approval['expires_at']) && strtotime($approval['expires_at']) < time()) {
                    Database::run('UPDATE discount_requests SET status = "expired" WHERE id = ?', [$reqId]);
                    throw new RuntimeException('That discount approval has expired.');
                }
                if ((int) $approval['requested_by'] !== (int) Auth::id()) {
                    throw new RuntimeException('That approval belongs to another till.');
                }

                $fingerprint = DiscountRequest::fingerprint($cart);
                if (!empty($approval['cart_fingerprint'])
                    && $approval['cart_fingerprint'] !== $fingerprint) {
                    throw new RuntimeException(
                        'The sale has changed since the discount was approved. '
                        . 'Ask for the discount again on the sale as it stands now.');
                }

                $approvedBy = (int) $approval['decided_by'];
                $stillSenior = $approvedBy && Database::value(
                    'SELECT COUNT(*) FROM users
                     WHERE id = ? AND status = "active" AND role IN ("admin","manager")',
                    [$approvedBy]);
                if (!$stillSenior) {
                    throw new RuntimeException(
                        'A discount must be approved by an active administrator or Store Manager.');
                }
                $percent = round(max(0.0, min(100.0, (float) $approval['percent'])), 2);
            }

            $discount = $percent > 0 ? min(round($subtotal * $percent / 100, 2), $subtotal) : 0.0;
            if ($percent <= 0) { $approvedBy = null; $discount = 0.0; }

            $total = round($subtotal - $discount, 2);
            $paid  = round((float) ($meta['amount_paid'] ?? 0), 2);
            if ($paid + 0.001 < $total) {
                throw new RuntimeException(sprintf(
                    'Amount paid is short by PHP %s. Total due is PHP %s.',
                    number_format($total - $paid, 2), number_format($total, 2)));
            }
            if (!is_finite($paid) || $paid < 0 || $paid > 9999999999.99) {
                throw new RuntimeException('Enter a valid amount paid.');
            }
            $change  = round($paid - $total, 2);
            $invoice = self::nextInvoice();

            Database::run(
                'INSERT INTO sales (invoice_no, user_id, customer_name, subtotal, discount,
                                    discount_percent, total, amount_paid, change_due,
                                    payment_method, discount_approved_by, idempotency_key, sale_date)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
                [$invoice, Auth::id(), (($meta['customer_name'] ?? '') ?: null), round($subtotal, 2),
                 $discount, $percent, $total, $paid, $change,
                 $meta['payment_method'] ?? 'Cash', $approvedBy,
                 $idemKey !== '' ? $idemKey : null]);
            $saleId = Database::lastId();

            // Spread the discount across the lines so per-item and per-category
            // net revenue still add up to the sale total. Largest-remainder:
            // give every line its floor, then hand the leftover centavos to the
            // lines with the biggest fractions, so the shares sum EXACTLY.
            $shares = self::allocate($discount, array_map(fn($l) => $l['line'], $lines));

            foreach ($lines as $k => $l) {
                Database::run(
                    'INSERT INTO sale_items (sale_id, product_id, product_name, sku,
                                             category_at_sale, unit_price, cost_price_at_sale,
                                             quantity, line_total, discount_share)
                     VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [$saleId, $l['p']['id'], $l['p']['name'], $l['p']['sku'],
                     $l['p']['category'], $l['p']['selling_price'], $l['p']['cost'],
                     $l['qty'], round($l['line'], 2), $shares[$k]]);

                // FR-05 - retire exactly the pieces that left the shop floor
                $codes = [];
                foreach ($l['units'] as $u) {
                    ProductUnit::markSold((int) $u['id'], $saleId);
                    $codes[] = $u['unit_code'];
                }
                Product::deduct((int) $l['p']['id'], $l['qty'], $invoice, implode(', ', $codes));
            }

            // Spend the approval. Still inside the transaction, still holding
            // the row lock taken above, so no other checkout can use it.
            if ($approval) {
                Database::run(
                    'UPDATE discount_requests
                     SET status = "used", sale_id = ?, consumed_at = NOW()
                     WHERE id = ? AND status = "approved" AND sale_id IS NULL', [$saleId, $reqId]);
                if (Database::value('SELECT sale_id FROM discount_requests WHERE id = ?', [$reqId]) != $saleId) {
                    throw new RuntimeException('That discount was used by another sale first.');
                }
            }

            Audit::log('Sales', 'CREATE',
                "Completed sale {$invoice} totalling PHP " . number_format($total, 2));

            if ($percent > 0) {
                $who = Database::value('SELECT username FROM users WHERE id = ?', [$approvedBy]);
                Audit::log('Sales', 'DISCOUNT',
                    DiscountRequest::pct($percent) . ' (' . number_format($discount, 2)
                    . ') off ' . $invoice . ' approved by ' . ($who ?: 'unknown'));
            }
            Database::commit();

            if ($percent > 0) {
                try {
                    $approverName = (string) Database::value(
                        'SELECT full_name FROM users WHERE id = ?', [$approvedBy]);
                    Notification::discountGiven($invoice, $discount,
                        Auth::user()['full_name'] ?? 'the till',
                        $approverName ?: 'an approver', $percent);
                } catch (Throwable $e) {
                    // The sale stands. A missing notice is not worth failing it.
                }
            }

            return ['ok' => true, 'sale_id' => $saleId, 'invoice' => $invoice];
        } catch (Throwable $e) {
            Database::rollback();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Split a peso amount across line totals in proportion, losing nothing.
     *
     * Rounding each share on its own leaves the shares a centavo or two off
     * the amount being split, which is how per-category figures stop adding
     * up to the sale. Largest remainder gives every line its floor and then
     * distributes the leftover centavos to the largest fractions.
     *
     * @param  float[] $weights line totals
     * @return float[]          one share per line, summing exactly to $amount
     */
    public static function allocate(float $amount, array $weights): array
    {
        $n = count($weights);
        if ($n === 0 || $amount <= 0) return array_fill(0, max($n, 0), 0.00);

        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) return array_fill(0, $n, 0.00);

        $cents  = (int) round($amount * 100);
        $floors = []; $rema = [];
        foreach ($weights as $i => $w) {
            $exact     = $cents * ($w / $totalWeight);
            $floors[$i] = (int) floor($exact);
            $rema[$i]   = $exact - $floors[$i];
        }
        $left = $cents - array_sum($floors);
        arsort($rema);
        foreach (array_keys($rema) as $i) {
            if ($left <= 0) break;
            $floors[$i]++; $left--;
        }
        ksort($floors);
        return array_map(fn($c) => round($c / 100, 2), $floors);
    }

    /**
     * The next invoice number, safe when two tills check out at once.
     *
     * The old version did COUNT(*) + 1 and then probed for a free number.
     * Two tills reached the same number because neither row was committed
     * yet, and deleting any sale made the counter walk backwards onto a
     * number that had already been printed on a receipt.
     *
     * invoice_counters is bumped with MySQL's LAST_INSERT_ID(expr) trick:
     * the UPDATE and the read of the new value are one atomic statement on
     * one row, so two sessions are handed different numbers even inside
     * concurrent transactions. Call this INSIDE the sale transaction.
     */
    public static function nextInvoice(): string
    {
        $year = (int) date('Y');

        Database::run(
            'INSERT INTO invoice_counters (year, next_no) VALUES (?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE next_no = LAST_INSERT_ID(next_no + 1)',
            [$year]);

        $seq = (int) Database::value('SELECT LAST_INSERT_ID()');

        return sprintf('INV-%04d%05d', $year, $seq);
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT s.*, u.full_name AS cashier,
                    a.full_name AS discount_approver, a.role AS discount_approver_role
             FROM sales s
             LEFT JOIN users u ON u.id = s.user_id
             LEFT JOIN users a ON a.id = s.discount_approved_by
             WHERE s.id = ?', [$id]);
    }

    public static function items(int $saleId): array
    {
        return Database::all('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id', [$saleId]);
    }

    public static function history(string $from, string $to, array $f = []): array
    {
        $sql  = 'SELECT s.*, u.full_name AS cashier,
                        a.full_name AS discount_approver, a.role AS discount_approver_role,
                        (SELECT COUNT(*) FROM sale_items i WHERE i.sale_id = s.id) AS line_count,
                        (SELECT COALESCE(SUM(i.quantity),0) FROM sale_items i WHERE i.sale_id = s.id) AS units
                 FROM sales s
                 LEFT JOIN users u ON u.id = s.user_id
                 LEFT JOIN users a ON a.id = s.discount_approved_by
                 WHERE DATE(s.sale_date) BETWEEN ? AND ?';
        $args = [$from, $to];
        if (!empty($f['cashier'])) { $sql .= ' AND s.user_id = ?'; $args[] = (int) $f['cashier']; }
        if (!empty($f['q'])) {
            $sql .= ' AND (s.invoice_no LIKE ? OR s.customer_name LIKE ?)';
            $args[] = '%' . $f['q'] . '%'; $args[] = '%' . $f['q'] . '%';
        }
        $sql .= ' ORDER BY s.sale_date DESC, s.id DESC';
        return Database::all($sql, $args);
    }

    public static function summary(string $from, string $to): array
    {
        return Database::one(
            'SELECT COUNT(*) txn, COALESCE(SUM(total),0) revenue,
                    COALESCE(SUM(discount),0) discounts, COALESCE(AVG(total),0) avg_sale
             FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?', [$from, $to]) ?? [];
    }

    /**
     * One person's own till for a day. A cashier may see what they themselves
     * rang up - they handed over the receipts - without being shown the shop's
     * takings as a whole, which belongs under Insights.
     */
    public static function summaryForUser(int $userId, string $day): array
    {
        return Database::one(
            'SELECT COUNT(*) txn, COALESCE(SUM(total),0) revenue,
                    COALESCE(SUM(discount),0) discounts
             FROM sales WHERE user_id = ? AND DATE(sale_date) = ?',
            [$userId, $day]) ?? ['txn' => 0, 'revenue' => 0, 'discounts' => 0];
    }

    /** How many pieces left the shop on a given day, store-wide. */
    public static function piecesSoldOn(string $day): int
    {
        return (int) Database::value(
            'SELECT COALESCE(SUM(i.quantity),0)
             FROM sale_items i JOIN sales s ON s.id = i.sale_id
             WHERE DATE(s.sale_date) = ?', [$day]);
    }

    /** Revenue grouped by day / week / month / year. */
    public static function trend(string $from, string $to, string $group = 'day'): array
    {
        $fmt = [
            'day'   => "DATE_FORMAT(sale_date,'%Y-%m-%d')",
            'week'  => "DATE_FORMAT(sale_date,'%x-W%v')",
            'month' => "DATE_FORMAT(sale_date,'%Y-%m')",
            'year'  => "DATE_FORMAT(sale_date,'%Y')",
        ][$group] ?? "DATE_FORMAT(sale_date,'%Y-%m-%d')";

        return Database::all(
            "SELECT {$fmt} AS bucket, COUNT(*) txn, COALESCE(SUM(total),0) revenue,
                    COALESCE(SUM(discount),0) discounts
             FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?
             GROUP BY bucket ORDER BY bucket", [$from, $to]);
    }

    public static function topProducts(string $from, string $to, int $limit = 10): array
    {
        return Database::all(
            'SELECT i.product_id, i.product_name, i.sku,
                    SUM(i.quantity) units, SUM((i.line_total - CASE WHEN i.category_at_sale IS NULL THEN IF(s.subtotal > 0, i.line_total * s.discount / s.subtotal, 0) ELSE i.discount_share END)) revenue
             FROM sale_items i JOIN sales s ON s.id = i.sale_id
             WHERE DATE(s.sale_date) BETWEEN ? AND ?
             GROUP BY i.product_id, i.product_name, i.sku
             ORDER BY revenue DESC LIMIT ' . (int) $limit, [$from, $to]);
    }

    public static function byCategory(string $from, string $to): array
    {
        return Database::all(
            'SELECT COALESCE(i.category_at_sale,"Not captured (legacy)") category,
                    SUM(i.quantity) units, SUM((i.line_total - CASE WHEN i.category_at_sale IS NULL THEN IF(s.subtotal > 0, i.line_total * s.discount / s.subtotal, 0) ELSE i.discount_share END)) revenue
             FROM sale_items i
             JOIN sales s ON s.id = i.sale_id
             LEFT JOIN products p ON p.id = i.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE DATE(s.sale_date) BETWEEN ? AND ?
             GROUP BY category ORDER BY revenue DESC', [$from, $to]);
    }

    public static function byCashier(string $from, string $to): array
    {
        return Database::all(
            'SELECT COALESCE(u.full_name,"Unknown") cashier, COUNT(*) txn,
                    COALESCE(SUM(s.total),0) revenue
             FROM sales s LEFT JOIN users u ON u.id = s.user_id
             WHERE DATE(s.sale_date) BETWEEN ? AND ?
             GROUP BY cashier ORDER BY revenue DESC', [$from, $to]);
    }

    public static function byPayment(string $from, string $to): array
    {
        return Database::all(
            'SELECT payment_method, COUNT(*) txn, COALESCE(SUM(total),0) revenue
             FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?
             GROUP BY payment_method ORDER BY revenue DESC', [$from, $to]);
    }

    /** Hour-of-day distribution - used by the analytics dashboard. */
    public static function byHour(string $from, string $to): array
    {
        return Database::all(
            'SELECT HOUR(sale_date) hr, COUNT(*) txn, COALESCE(SUM(total),0) revenue
             FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?
             GROUP BY hr ORDER BY hr', [$from, $to]);
    }

    // ------------------------------------------------------------------
    // FR-10 - Category Sales. The shipment owner is asked for stock by
    // category, never by a named product, because nobody can promise which
    // individual pieces a container will hold. These two queries feed the
    // report the manager takes to that monthly meeting.
    // ------------------------------------------------------------------

    /** One row per day per category - the source of the daily matrix. */
    public static function dailyByCategory(string $from, string $to): array
    {
        return Database::all(
            'SELECT DATE(s.sale_date) day,
                    COALESCE(i.category_at_sale,"Not captured (legacy)") category,
                    SUM(i.quantity)   units,
                    SUM((i.line_total - CASE WHEN i.category_at_sale IS NULL THEN IF(s.subtotal > 0, i.line_total * s.discount / s.subtotal, 0) ELSE i.discount_share END)) revenue
             FROM sale_items i
             JOIN sales s      ON s.id = i.sale_id
             LEFT JOIN products p   ON p.id = i.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE DATE(s.sale_date) BETWEEN ? AND ?
             GROUP BY day, category
             ORDER BY day, category', [$from, $to]);
    }

    /**
     * Per-category performance over the period, joined to what is still on
     * the floor right now. Every active category is returned even when it
     * sold nothing - "we shifted none of these" is exactly the kind of thing
     * the owner needs to hear before the next container is loaded.
     */
    public static function categoryPerformance(string $from, string $to): array
    {
        $rows = Database::all(
            'SELECT c.id, c.name category, c.code,
                    0 units, 0 revenue, NULL cost, 0 txn, 0 items_sold,
                    COUNT(p.id) items_listed, COALESCE(SUM(p.quantity),0) pieces_left,
                    COALESCE(SUM(p.quantity * p.cost_price),0) stock_value
             FROM categories c LEFT JOIN products p ON p.category_id = c.id AND p.status = "active"
             GROUP BY c.id, c.name, c.code');
        $sold = Database::all(
            'SELECT COALESCE(i.category_at_sale,"Not captured (legacy)") category,
                    SUM(i.quantity) units, SUM((i.line_total - CASE WHEN i.category_at_sale IS NULL THEN IF(s.subtotal > 0, i.line_total * s.discount / s.subtotal, 0) ELSE i.discount_share END)) revenue,
                    CASE WHEN COUNT(i.cost_price_at_sale) = COUNT(*) THEN SUM(i.quantity * i.cost_price_at_sale) ELSE NULL END cost,
                    COUNT(DISTINCT s.id) txn, COUNT(DISTINCT i.product_id) items_sold
             FROM sale_items i JOIN sales s ON s.id = i.sale_id
             WHERE DATE(s.sale_date) BETWEEN ? AND ? GROUP BY category', [$from, $to]);
        foreach ($sold as $sale) {
            $match = null;
            foreach ($rows as $k => $row) {
                if ($row['category'] === $sale['category']) { $match = $k; break; }
            }
            if ($match === null) {
                $rows[] = $sale + ['id'=>0, 'code'=>null, 'items_listed'=>0, 'pieces_left'=>0, 'stock_value'=>0];
            } else {
                $rows[$match] = array_merge($rows[$match], $sale);
            }
        }
        return $rows;
    }
}
