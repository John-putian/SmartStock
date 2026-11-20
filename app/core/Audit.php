<?php
/** FR-08 - Audit trail for transaction and stock modifications. */
class Audit
{
    public static function log(string $module, string $action, string $description): void
    {
        $u = Auth::user();
        Database::run(
            'INSERT INTO audit_logs (user_id, username, module, action, description, ip_address)
             VALUES (?,?,?,?,?,?)',
            [
                $u['id']       ?? null,
                $u['username'] ?? 'system',
                $module,
                $action,
                mb_substr($description, 0, 250),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]
        );
    }
}
