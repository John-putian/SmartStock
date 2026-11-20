<?php
/** FR-02, FR-03, FR-05, FR-06 - Inventory items. */
class Product
{
    public static function create(array $d): int
    {
        return Database::atomic(function () use ($d) {
            
            return self::createInternal($d);
        });
    }
    public static function update(int $id, array $d): void
    {
        Database::atomic(function () use ($id, $d) {
            $locked = Database::one('SELECT id FROM products WHERE id = ? FOR UPDATE', [$id]);
            if (!$locked) throw new RuntimeException('Item not found.');
            return self::updateInternal($id, $d);
        });
    }
    public static function stockIn(int $id, int $qty, string $notes = ''): array
    {
        return Database::atomic(function () use ($id, $qty, $notes) {
            $locked = Database::one('SELECT id FROM products WHERE id = ? FOR UPDATE', [$id]);
            if (!$locked) throw new RuntimeException('Item not found.');
            return self::stockInInternal($id, $qty, $notes);
        });
    }
    public static function adjust(int $id, int $newQty, string $notes = ''): void
    {
        Database::atomic(function () use ($id, $newQty, $notes) {
            $locked = Database::one('SELECT id FROM products WHERE id = ? FOR UPDATE', [$id]);
            if (!$locked) throw new RuntimeException('Item not found.');
            return self::adjustInternal($id, $newQty, $notes);
        });
    }
    public static function archive(int $id): void
    {
        Database::atomic(function () use ($id) {
            $locked = Database::one('SELECT id FROM products WHERE id = ? FOR UPDATE', [$id]);
            if (!$locked) throw new RuntimeException('Item not found.');
            return self::archiveInternal($id);
        });
    }

    /** Paginated / filtered list of items with category name and stock status. */
    public static function search(array $f = []): array
    {
        $sql = 'SELECT p.*, c.name AS category_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.status = "active"';
        $args = [];

        if (!empty($f['q'])) {
            $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ? OR p.serial_no LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($args, $like, $like, $like, $like);
        }
        if (!empty($f['category'])) {
            $sql .= ' AND p.category_id = ?';
            $args[] = (int) $f['category'];
        }
        if (!empty($f['condition'])) {
            $sql .= ' AND p.condition_grade = ?';
            $args[] = $f['condition'];
        }
        if (!empty($f['stock'])) {
            if ($f['stock'] === 'out')  $sql .= ' AND p.quantity <= 0';
            if ($f['stock'] === 'low')  $sql .= ' AND p.quantity > 0 AND p.quantity <= p.reorder_level';
            if ($f['stock'] === 'in')   $sql .= ' AND p.quantity > p.reorder_level';
        }

        $sort = [
            'name'  => 'p.name ASC',
            'stock' => 'p.quantity ASC',
            'price' => 'p.selling_price DESC',
            'new'   => 'p.created_at DESC, p.id DESC',
        ][$f['sort'] ?? 'name'] ?? 'p.name ASC';
        $sql .= ' ORDER BY ' . $sort;

        if (!empty($f['limit'])) {
            $sql .= ' LIMIT ' . (int) $f['limit'];
        }
        return Database::all($sql, $args);
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT p.*, c.name AS category_name
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?', [$id]);
    }

    public static function findBySku(string $sku): ?array
    {
        return Database::one('SELECT * FROM products WHERE sku = ? AND status = "active"', [$sku]);
    }

    /**
     * Auto-generate the next item barcode for a category, e.g. AA-FUR-0033.
     * The category code is baked into the barcode, and each physical piece
     * then extends it with its own number (AA-FUR-0033-02).
     */
    public static function nextSku(int $categoryId): string
    {
        $code = (string) (Database::value(
            'SELECT code FROM categories WHERE id = ?', [$categoryId]) ?: 'GEN');
        $n = (int) Database::value('SELECT COALESCE(MAX(id),0) + 1 FROM products');
        return sprintf('AA-%s-%04d', strtoupper($code), $n);
    }

    private static function createInternal(array $d): int
    {
        $automatic = trim($d['sku']) === '';
        if ($automatic) $d['sku'] = 'TMP-' . bin2hex(random_bytes(12));
        Database::run(
            'INSERT INTO products
             (sku, serial_no, name, category_id, brand, description, condition_grade,
              cost_price, selling_price, quantity, image_path, reorder_level)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$d['sku'], $d['serial_no'], $d['name'], $d['category_id'], $d['brand'],
             $d['description'], $d['condition_grade'], $d['cost_price'],
             $d['selling_price'], $d['quantity'], $d['image_path'] ?? null,
             $d['reorder_level']]);
        $id  = Database::lastId();
        if ($automatic) {
            $code = Database::value('SELECT code FROM categories WHERE id = ?', [$d['category_id']]) ?: 'GEN';
            $d['sku'] = sprintf('AA-%s-%04d', strtoupper($code), $id);
            Database::run('UPDATE products SET sku = ? WHERE id = ?', [$d['sku'], $id]);
        }
        $qty = (int) $d['quantity'];
        if ($qty > 0) {
            // one barcode per physical piece
            ProductUnit::generate($id, $qty, $d['condition_grade'] ?? null);
            ProductUnit::syncQuantity($id);
            self::movement($id, 'in', $qty, $qty,
                           'NEW-ITEM', 'Initial stock on item registration');
        }
        Audit::log('Inventory', 'CREATE', 'Registered item ' . $d['sku'] . ' - ' . $d['name']);
        return $id;
    }

    private static function updateInternal(int $id, array $d): void
    {
        $before = self::find($id);
        Database::run(
            'UPDATE products SET serial_no=?, name=?, category_id=?, brand=?, description=?,
             condition_grade=?, cost_price=?, selling_price=?, image_path=?, reorder_level=?
             WHERE id=?',
            [$d['serial_no'], $d['name'], $d['category_id'], $d['brand'], $d['description'],
             $d['condition_grade'], $d['cost_price'], $d['selling_price'],
             $d['image_path'] ?? null, $d['reorder_level'], $id]);

        // Quantity is changed through an explicit adjustment so the ledger stays truthful.
        $newQty = (int) $d['quantity'];
        if ($before && $newQty !== (int) $before['quantity']) {
            self::adjust($id, $newQty, 'Quantity corrected through item edit form');
        }
        Audit::log('Inventory', 'UPDATE', 'Updated item ' . ($before['sku'] ?? $id) . ' - ' . $d['name']);

        // A price move gets its own line in the trail, whoever made it.
        $oldPrice = (float) ($before['selling_price'] ?? 0);
        $newPrice = (float) $d['selling_price'];
        if ($before && abs($newPrice - $oldPrice) >= 0.01) {
            Audit::log('Price Control', 'PRICE',
                ($before['sku'] ?? $id) . ' price ' . number_format($oldPrice, 2) .
                ' to ' . number_format($newPrice, 2) .
                ($newPrice < $oldPrice ? ' (reduction by ' . role_label(Auth::role()) . ')' : ''));
        }
    }

    /**
     * Move the selling price on its own, leaving every other field alone.
     * Used when an approved reduction is finally applied. Price changes get
     * their own audit action so they can be picked out of the trail at a
     * glance - it is the number the shop cares most about.
     */
    public static function setPrice(int $id, float $price, string $why = ''): void
    {
        $before = self::find($id);
        if (!$before) {
            return;
        }
        Database::run('UPDATE products SET selling_price = ? WHERE id = ?',
            [round($price, 2), $id]);

        Audit::log('Price Control', 'PRICE',
            ($before['sku'] ?? $id) . ' price ' . number_format((float) $before['selling_price'], 2) .
            ' to ' . number_format($price, 2) . ($why ? ' - ' . $why : ''));
    }

    private static function archiveInternal(int $id): void
    {
        $p = self::find($id);
        Database::run('UPDATE products SET status = "archived" WHERE id = ?', [$id]);
        Audit::log('Inventory', 'ARCHIVE', 'Archived item ' . ($p['sku'] ?? $id));
    }

    /** Add received stock (container delivery / restock). */
    private static function stockInInternal(int $id, int $qty, string $notes = ''): array
    {
        $p     = self::find($id);
        $codes = ProductUnit::generate($id, $qty, $p['condition_grade'] ?? null, $notes ?: null);
        $bal   = ProductUnit::syncQuantity($id);
        self::movement($id, 'in', $qty, $bal, 'STOCK-IN', $notes ?: 'Stock received');
        Audit::log('Inventory', 'STOCK-IN',
            "Received {$qty} piece(s) for product #{$id}: " . implode(', ', $codes));
        Notification::stockChanged($id);
        return $codes;
    }

    /** Set stock to an exact counted value (physical count correction). */
    private static function adjustInternal(int $id, int $newQty, string $notes = ''): void
    {
        $old = ProductUnit::availableCount($id);
        $p   = self::find($id);

        if ($newQty > $old) {
            ProductUnit::generate($id, $newQty - $old, $p['condition_grade'] ?? null,
                                  $notes ?: 'Found on physical count');
        } elseif ($newQty < $old) {
            ProductUnit::retire($id, $old - $newQty, $notes ?: 'Physical count shortfall');
        }
        $bal = ProductUnit::syncQuantity($id);

        self::movement($id, 'adjust', abs($newQty - $old), $bal, 'ADJUST',
                       $notes ?: "Adjusted from {$old} to {$newQty}");
        Audit::log('Inventory', 'ADJUST', "Adjusted product #{$id} from {$old} to {$bal}");
        Notification::stockChanged($id);
    }

    /**
     * FR-05 - record the stock-out after a completed sale. The individual
     * pieces are marked sold by Sale::checkout(); this keeps products.quantity
     * and the movement ledger in step with the unit register.
     */
    public static function deduct(int $id, int $qty, string $invoice, string $codes = ''): void
    {
        $bal = ProductUnit::syncQuantity($id);
        self::movement($id, 'out', $qty, $bal, $invoice,
            'POS sale' . ($codes !== '' ? ' - ' . $codes : ''));
        Notification::stockChanged($id);
    }

    public static function movement(int $pid, string $type, int $qty, int $balance,
                                    string $ref, string $notes): void
    {
        Database::run(
            'INSERT INTO stock_movements (product_id, type, quantity, balance, reference, notes, user_id)
             VALUES (?,?,?,?,?,?,?)',
            [$pid, $type, $qty, $balance, $ref, $notes, Auth::id()]);
    }

    public static function movements(int $pid, int $limit = 40): array
    {
        return Database::all(
            'SELECT m.*, u.full_name FROM stock_movements m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.product_id = ? ORDER BY m.created_at DESC, m.id DESC LIMIT ' . (int) $limit,
            [$pid]);
    }

    /**
     * The last movements across the whole shop - what came in off the
     * container and what went out of the door. This is the shop floor's view
     * of the day, and the one thing a cashier is meant to keep an eye on.
     */
    public static function recentMovements(int $limit = 12): array
    {
        return Database::all(
            'SELECT m.*, p.name AS product_name, p.sku, c.name AS category_name,
                    u.full_name
             FROM stock_movements m
             JOIN products p        ON p.id = m.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN users u      ON u.id = m.user_id
             ORDER BY m.created_at DESC, m.id DESC LIMIT ' . max(1, min(100, $limit)));
    }

    /** FR-06 - items at or below their reorder level. */
    public static function alerts(): array
    {
        return Database::all(
            'SELECT p.*, c.name AS category_name,
                    COALESCE(s.sold_30, 0) AS sold_30
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN (
                SELECT si.product_id, SUM(si.quantity) sold_30
                FROM sale_items si JOIN sales s ON s.id = si.sale_id
                WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY si.product_id
             ) s ON s.product_id = p.id
             WHERE p.status = "active" AND p.quantity <= p.reorder_level
             ORDER BY p.quantity ASC, sold_30 DESC');
    }

    public static function counts(): array
    {
        return Database::one(
            'SELECT COUNT(*) total,
                    SUM(quantity <= 0) out_stock,
                    SUM(quantity > 0 AND quantity <= reorder_level) low_stock,
                    SUM(quantity > reorder_level) in_stock,
                    COALESCE(SUM(quantity),0) units,
                    COALESCE(SUM(quantity * cost_price),0) stock_value,
                    COALESCE(SUM(quantity * selling_price),0) retail_value
             FROM products WHERE status = "active"') ?? [];
    }
}
