<?php
/** FR-08 - audit trail viewer (Administrator / Manager only). */
class AuditController
{
    public static function index(): void
    {
        $module = (string) input('module', '');
        $user   = (string) input('user', '');
        $q      = trim((string) input('q', ''));
        $from   = (string) input('from', date('Y-m-d', strtotime('-30 days')));
        $to     = (string) input('to', date('Y-m-d'));

        $sql  = 'SELECT a.*, u.full_name FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE DATE(a.created_at) BETWEEN ? AND ?';
        $args = [$from, $to];
        if ($module !== '') { $sql .= ' AND a.module = ?'; $args[] = $module; }
        if ($user   !== '') { $sql .= ' AND a.user_id = ?'; $args[] = (int) $user; }
        if ($q      !== '') {
            $sql .= ' AND (a.description LIKE ? OR a.action LIKE ? OR a.username LIKE ?)';
            $like = '%' . $q . '%';
            array_push($args, $like, $like, $like);
        }
        $sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT 400';

        view('audit/index', [
            'logs'    => Database::all($sql, $args),
            'modules' => Database::all('SELECT DISTINCT module FROM audit_logs ORDER BY module'),
            'users'   => Database::all('SELECT id, full_name FROM users ORDER BY full_name'),
            'byAction'=> Database::all(
                'SELECT action, COUNT(*) n FROM audit_logs
                 WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY action ORDER BY n DESC LIMIT 8',
                [$from, $to]),
            'f' => compact('module', 'user', 'q', 'from', 'to'),
        ], 'Audit Trail');
    }
}
