<?php
/**
 * FR-11b - outgoing mail.
 *
 * One rule governs this whole class, and it is the same rule that governs
 * Notification: sending a message must never break the thing that triggered
 * it. Every failure is caught and recorded, never thrown. A mail server that
 * is slow, wrong or unreachable must not hang a page, and it certainly must
 * not do so in front of a panel.
 *
 * Mail goes out over SMTP rather than PHP's mail(), because a XAMPP install
 * has no mail transport at all - mail() simply returns false.
 */

use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    private static ?array $cfg = null;

    /**
     * PHPMailer is a 180 KB library that most page loads never touch, so it
     * is loaded at the moment something is actually being sent rather than
     * on every request through config.php.
     */
    private static function loadLibrary(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $dir = APP_PATH . '/vendor/phpmailer/';
        require_once $dir . 'Exception.php';
        require_once $dir . 'PHPMailer.php';
        require_once $dir . 'SMTP.php';
        $loaded = true;
    }

    /** The last thing that went wrong, for the settings screen to show. */
    public static string $lastError = '';

    public static function config(): array
    {
        if (self::$cfg === null) {
            $path = BASE_PATH . '/config/mail.php';
            self::$cfg = is_file($path) ? (array) require $path : [];
        }
        return self::$cfg;
    }

    /** True when there is enough here to actually send something. */
    public static function ready(): bool
    {
        $c = self::config();
        return !empty($c['enabled'])
            && !empty($c['host'])
            && !empty($c['username'])
            && !empty($c['password']);
    }

    /** Is this kind of notice one the shop asked to receive by email? */
    public static function sends(string $type): bool
    {
        $c = self::config();
        return in_array($type, (array) ($c['send_types'] ?? []), true);
    }

    /**
     * Send one message. Returns true only if the server accepted it.
     * Never throws.
     */
    public static function send(string $to, string $toName,
                                string $subject, string $html,
                                string $plain = ''): bool
    {
        self::$lastError = '';

        if (!self::ready()) {
            self::$lastError = 'Mail is switched off, or config/mail.php is '
                             . 'missing the sending address or app password.';
            return false;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = '"' . $to . '" is not a valid email address.';
            return false;
        }

        $c = self::config();
        self::loadLibrary();

        try {
            $m = new PHPMailer(true);          // true: throw, so we can catch
            $m->isSMTP();
            $m->Host       = $c['host'];
            $m->Port       = (int) $c['port'];
            $m->SMTPAuth   = true;
            $m->Username   = $c['username'];
            // Gmail prints app passwords in groups of four. People paste them
            // that way, and the spaces are not part of the secret.
            $m->Password   = str_replace(' ', '', (string) $c['password']);
            $m->SMTPSecure = ($c['encryption'] ?? 'tls') === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $m->Timeout    = (int) ($c['timeout'] ?? 8);
            $m->CharSet    = 'UTF-8';          // the peso sign depends on this

            $m->setFrom($c['username'], (string) ($c['from_name'] ?? 'Smart Stock'));
            $m->addAddress($to, $toName !== '' ? $toName : $to);
            $m->isHTML(true);
            $m->Subject = $subject;
            $m->Body    = $html;
            $m->AltBody = $plain !== '' ? $plain : strip_tags($html);

            $m->send();
            return true;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Render an email template from app/views/emails and return the HTML.
     * Kept separate from send() so the same template can be previewed on
     * screen without anything being posted.
     */
    public static function render(string $template, array $data = []): string
    {
        $file = APP_PATH . '/views/emails/' . $template . '.php';
        if (!is_file($file)) {
            return '';
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    /**
     * Email a notice that has already been written to the bell, to whoever
     * owns it - but only if that account has an address and only for the
     * kinds of notice the shop asked for.
     *
     * Returns the number of messages the server accepted.
     */
    public static function sendNotice(string $type, string $subject,
                                      array $data, array $userIds = []): int
    {
        if (!self::sends($type) || !self::ready()) {
            return 0;
        }

        try {
            $sql  = 'SELECT full_name, email FROM users
                     WHERE status = "active" AND email IS NOT NULL AND email <> ""';
            $args = [];
            if ($userIds) {
                $in   = implode(',', array_fill(0, count($userIds), '?'));
                $sql .= " AND id IN ($in)";
                $args = $userIds;
            }
            $rows = Database::all($sql, $args);
        } catch (Throwable $e) {
            return 0;
        }

        $html = self::render($type, $data);
        if ($html === '') {
            return 0;
        }

        $sent = 0;
        foreach ($rows as $r) {
            if (self::send((string) $r['email'], (string) $r['full_name'],
                           $subject, $html)) {
                $sent++;
            }
        }

        if ($sent > 0) {
            try {
                Audit::log('Notifications', 'EMAIL',
                    $sent . ' "' . $type . '" email(s) sent');
            } catch (Throwable $e) {
                // An unrecorded send is still a send.
            }
        }
        return $sent;
    }
}
