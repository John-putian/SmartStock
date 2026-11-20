<?php
/** Product categories. */
class Category
{
    public static function all(): array
    {
        return Database::all(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status="active") AS item_count,
                    (SELECT COALESCE(SUM(p.quantity),0) FROM products p WHERE p.category_id = c.id AND p.status="active") AS unit_count
             FROM categories c ORDER BY c.name');
    }

    /** Every category - used by the item form, so empty ones can be filled. */
    public static function options(): array
    {
        return Database::all('SELECT id, name FROM categories ORDER BY name');
    }

    /**
     * Only categories that currently hold stock. Surplus arrives at random,
     * so a category sits empty until a container happens to bring something
     * for it; showing empty options in the POS and inventory filters would
     * just be dead choices for the cashier.
     */
    public static function optionsInUse(): array
    {
        return Database::all(
            'SELECT c.id, c.name
             FROM categories c
             JOIN products p ON p.category_id = c.id AND p.status = "active"
             GROUP BY c.id, c.name
             ORDER BY c.name');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    public static function create(string $name, string $desc): int
    {
        Database::run('INSERT INTO categories (name, description) VALUES (?,?)', [$name, $desc]);
        $id = Database::lastId();
        Audit::log('Inventory', 'CREATE', 'Added category "' . $name . '"');
        return $id;
    }

    public static function update(int $id, string $name, string $desc): void
    {
        Database::run('UPDATE categories SET name=?, description=? WHERE id=?', [$name, $desc, $id]);
        Audit::log('Inventory', 'UPDATE', 'Updated category "' . $name . '"');
    }

    public static function delete(int $id): bool
    {
        $count = (int) Database::value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]);
        if ($count > 0) return false;
        Database::run('DELETE FROM categories WHERE id = ?', [$id]);
        Audit::log('Inventory', 'DELETE', 'Deleted category #' . $id);
        return true;
    }
}
