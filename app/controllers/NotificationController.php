<?php
/**
 * FR-11 - the notification centre.
 *
 * Everything here is scoped to the signed-in account. A notice belongs to one
 * person, so opening or clearing one can only ever touch your own row - the
 * account id comes from the session and never from the request.
 */
class NotificationController
{
    public static function index(): void
    {
        $me     = (int) Auth::id();
        $status = (string) input('status', '');
        $type   = (string) input('type', '');

        if (!in_array($status, ['', 'unread', 'read'], true)) {
            $status = '';
        }
        if ($type !== '' && !isset(Notification::TYPES[$type])) {
            $type = '';
        }

        view('notifications/index', [
            'items'   => Notification::forUser($me, [
                'status' => $status, 'type' => $type, 'limit' => 100,
            ]),
            'status'  => $status,
            'type'    => $type,
            'unread'  => Notification::unreadCount($me),
            'counts'  => Notification::countsByType($me),
        ], 'Notifications');
    }

    /**
     * Mark one notice read and go wherever it points. This is what the bell
     * dropdown links to, so reading and acting are a single click.
     */
    public static function open(): void
    {
        $me = (int) Auth::id();
        $id = (int) input('id');

        $n = Database::one(
            'SELECT * FROM notifications WHERE id = ? AND user_id = ?', [$id, $me]);

        if (!$n) {
            flash('warning', 'That notification is no longer available.');
            redirect('notifications');
        }

        Notification::markRead($id, $me);

        if (!empty($n['link_page'])) {
            $params = [];
            parse_str((string) $n['link_params'], $params);
            redirect($n['link_page'], is_array($params) ? $params : []);
        }
        redirect('notifications');
    }

    public static function readAll(): void
    {
        verify_csrf();
        $n = Notification::markAllRead((int) Auth::id());
        flash('success', $n > 0
            ? num($n) . ' notification(s) marked as read.'
            : 'There was nothing left to mark as read.');
        redirect('notifications');
    }
}
