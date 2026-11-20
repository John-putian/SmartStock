<?php
/** FR-01 - User accounts and roles. */
class User
{
    public static function all(): array
    {
        return Database::all(
            'SELECT u.id, u.full_name, u.username, u.role, u.contact_no, u.status, u.last_login, u.created_at, (SELECT COUNT(*) FROM sales s WHERE s.user_id = u.id) AS sales_count
             FROM users u ORDER BY u.role, u.full_name');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** Guards against the shop locking itself out of its own system. */
    public static function countActiveAdmins(): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM users WHERE role = "admin" AND status = "active"');
    }

    public static function usernameTaken(string $u, ?int $exceptId = null): bool
    {
        $sql  = 'SELECT COUNT(*) FROM users WHERE username = ?';
        $args = [$u];
        if ($exceptId) { $sql .= ' AND id <> ?'; $args[] = $exceptId; }
        return (int) Database::value($sql, $args) > 0;
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO users (full_name, username, password_hash, role, contact_no, status)
             VALUES (?,?,?,?,?,?)',
            [$d['full_name'], $d['username'], password_hash($d['password'], PASSWORD_DEFAULT),
             $d['role'], $d['contact_no'], $d['status']]);
        $id = Database::lastId();
        Audit::log('Users', 'CREATE', 'Created ' . $d['role'] . ' account "' . $d['username'] . '"');
        return $id;
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE users SET full_name=?, username=?, role=?, contact_no=?, status=? WHERE id=?',
            [$d['full_name'], $d['username'], $d['role'], $d['contact_no'], $d['status'], $id]);
        if (!empty($d['password'])) {
            Database::run('UPDATE users SET password_hash = ? WHERE id = ?',
                [password_hash($d['password'], PASSWORD_DEFAULT), $id]);
        }
        Audit::log('Users', 'UPDATE', 'Updated account "' . $d['username'] . '"');
    }

    public static function delete(int $id): void
    {
        $u = self::find($id);
        Database::run('UPDATE users SET status = "inactive" WHERE id = ?', [$id]);
        Audit::log('Users', 'DEACTIVATE', 'Deactivated account "' . ($u['username'] ?? $id) . '"');
    }
}
