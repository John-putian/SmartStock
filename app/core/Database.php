<?php
/**
 * Thin PDO wrapper - single shared connection for the whole application.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $cfg = require BASE_PATH . '/config/database.php';
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']
            );
            try {
                self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                // Keep MySQL's NOW()/CURDATE() on the same clock as PHP, so
                // "today" means the same thing in both layers.
                self::$pdo->exec("SET time_zone = '" . date('P') . "'");
            } catch (PDOException $e) {
                http_response_code(500);
                echo '<div style="font-family:system-ui;padding:40px;max-width:640px;margin:auto">'
                   . '<h2 style="color:#b91c1c">Database connection failed</h2>'
                   . '<p>The database is unavailable. Check the server and connection settings.</p>'
                   . '<p>Start <strong>MySQL</strong> in the XAMPP Control Panel and make sure the '
                   . '<code>aa_inventory</code> database has been imported '
                   . '(see <code>database/schema.sql</code> and <code>database/seed.sql</code>).</p></div>';
                exit;
            }
        }
        return self::$pdo;
    }

    /** Run a prepared statement and return the statement object. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch all rows. */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** Fetch a single row (or null). */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch a single scalar value. */
    public static function value(string $sql, array $params = [])
    {
        $row = self::run($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? null : $row[0];
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /** Join an existing transaction or commit/roll back the entire inventory operation. */
    public static function atomic(callable $operation)
    {
        $owns = !self::pdo()->inTransaction();
        if ($owns) self::begin();
        try {
            $result = $operation();
            if ($owns) self::commit();
            return $result;
        } catch (Throwable $error) {
            if ($owns) self::rollback();
            throw $error;
        }
    }

    public static function begin(): void  { self::pdo()->beginTransaction(); }
    public static function commit(): void { self::pdo()->commit(); }
    public static function rollback(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }
}
