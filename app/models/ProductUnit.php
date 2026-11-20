<?php
/**
 * FR-02 / FR-03 - individual physical units.
 *
 * Second-hand stock is one-of-a-kind, so the system tracks each PIECE, not
 * just a quantity. A one-off wooden table has a single unit; five matching
 * chairs have five, each with its own barcode, its own condition grade and
 * its own remarks, and each sold separately.
 *
 * Barcode:  AA-FUR-0007-03
 *           store · category code · item number · unit number
 */
class ProductUnit
{
    /**
     * Barcode prefix for an item - simply the item's own barcode, so a unit
     * code always reads as "item barcode + piece number".
     * e.g. item AA-FUR-0007 -> pieces AA-FUR-0007-01, -02, -03 ...
     */
    public static function prefix(int $productId): string
    {
        return (string) (Database::value(
            'SELECT sku FROM products WHERE id = ?', [$productId]) ?: 'AA-GEN-0000');
    }

    /** Create $qty new labelled units for an item and return their codes. */
    public static function generate(int $productId, int $qty, ?string $condition = null,
                                    ?string $notes = null): array
    {
        $qty = max(0, $qty);
        if ($qty === 0) return [];

        $prefix = self::prefix($productId);
        $next   = (int) Database::value(
            'SELECT COALESCE(MAX(unit_no), 0) FROM product_units WHERE product_id = ?',
            [$productId]);

        $codes = [];
        for ($i = 1; $i <= $qty; $i++) {
            $no   = $next + $i;
            $code = sprintf('%s-%02d', $prefix, $no);
            Database::run(
                'INSERT INTO product_units (product_id, unit_code, unit_no, condition_grade, notes)
                 VALUES (?,?,?,?,?)',
                [$productId, $code, $no, $condition, $notes]);
            $codes[] = $code;
        }
        return $codes;
    }

    /** Every unit of an item, newest first, with its sale reference if sold. */
    public static function forProduct(int $productId): array
    {
        return Database::all(
            'SELECT u.*, s.invoice_no, s.sale_date
             FROM product_units u
             LEFT JOIN sales s ON s.id = u.sale_id
             WHERE u.product_id = ?
             ORDER BY FIELD(u.status,"in_stock","reserved","sold","retired"), u.unit_no',
            [$productId]);
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT u.*, p.name, p.selling_price, p.image_path, p.sku, p.status AS product_status,
                    c.name AS category_name
             FROM product_units u
             JOIN products p ON p.id = u.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE u.id = ?', [$id]);
    }

    /** Look a unit up by its printed barcode. */
    public static function findByCode(string $code): ?array
    {
        return Database::one(
            'SELECT u.*, p.name, p.selling_price, p.image_path, p.sku, p.status AS product_status,
                    c.name AS category_name
             FROM product_units u
             JOIN products p ON p.id = u.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE u.unit_code = ?', [trim($code)]);
    }

    /**
     * The next pieces of an item that are free to sell, oldest stock first,
     * skipping anything already sitting in the current sale.
     */
    public static function available(int $productId, int $limit = 1, array $exclude = []): array
    {
        $sql  = 'SELECT * FROM product_units
                 WHERE product_id = ? AND status = "in_stock"';
        $args = [$productId];
        if ($exclude) {
            $sql .= ' AND id NOT IN (' . implode(',', array_map('intval', $exclude)) . ')';
        }
        $sql .= ' ORDER BY received_at, unit_no LIMIT ' . (int) $limit;
        return Database::all($sql, $args);
    }

    public static function availableCount(int $productId): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM product_units WHERE product_id = ? AND status = "in_stock"',
            [$productId]);
    }

    /** FR-05 - mark a piece sold and tie it to the sale that took it. */
    public static function markSold(int $unitId, int $saleId): void
    {
        Database::run(
            'UPDATE product_units SET status = "sold", sale_id = ?, sold_at = NOW()
             WHERE id = ? AND status = "in_stock"', [$saleId, $unitId]);
    }

    /** Take pieces off the floor (write-off, damage, physical count shortfall). */
    public static function retire(int $productId, int $qty, string $notes = ''): int
    {
        $units = self::available($productId, $qty);
        foreach ($units as $u) {
            Database::run(
                'UPDATE product_units SET status = "retired", notes = ? WHERE id = ?',
                [trim(($u['notes'] ? $u['notes'] . ' · ' : '') . ($notes ?: 'Retired')), $u['id']]);
        }
        return count($units);
    }

    public static function update(int $id, ?string $condition, ?string $notes): void
    {
        Database::run('UPDATE product_units SET condition_grade = ?, notes = ? WHERE id = ?',
            [$condition ?: null, $notes ?: null, $id]);
        Audit::log('Inventory', 'UPDATE', 'Updated unit record #' . $id);
    }

    /**
     * Keep products.quantity equal to the number of pieces actually on the
     * floor. The unit register is the source of truth.
     */
    public static function syncQuantity(int $productId): int
    {
        $n = self::availableCount($productId);
        Database::run('UPDATE products SET quantity = ? WHERE id = ?', [$n, $productId]);
        return $n;
    }

    /** Units consumed by one sale, for printing on the receipt. */
    public static function forSale(int $saleId): array
    {
        $rows = Database::all(
            'SELECT product_id, unit_code FROM product_units WHERE sale_id = ? ORDER BY unit_code',
            [$saleId]);
        $byProduct = [];
        foreach ($rows as $r) {
            $byProduct[(int) $r['product_id']][] = $r['unit_code'];
        }
        return $byProduct;
    }

    /** Counts for the unit register summary. */
    public static function stats(): array
    {
        return Database::one(
            'SELECT COUNT(*) total,
                    SUM(status = "in_stock") in_stock,
                    SUM(status = "sold")     sold,
                    SUM(status = "retired")  retired
             FROM product_units') ?? [];
    }
}
