<?php
/**
 * FR-11b - email notifications, administrator only.
 *
 * Three things live here: showing whether mail is configured, previewing
 * exactly what the takings email looks like, and sending one on demand so
 * the setup can be proved without waiting for a new day.
 *
 * The app password itself is NOT edited here. It is typed once into
 * config/mail.php, a file the web server denies access to. A password field
 * on a web page is one more place a secret can leak from, and this one buys
 * nothing: the file is opened once and never again.
 */
class MailController
{
    /** The settings and status screen. */
    public static function index(): void
    {
        $cfg = Mailer::config();

        view('mail/index', [
            'cfg'        => $cfg,
            'ready'      => Mailer::ready(),
            'recipients' => Database::all(
                'SELECT id, full_name, username, role, email
                 FROM users
                 WHERE status = "active" AND role IN ("admin","manager")
                 ORDER BY FIELD(role,"admin","manager"), full_name'),
            'digest'     => Notification::latestDigestData(),
        ], 'Email Notifications');
    }

    /**
     * Render the takings email on screen, exactly as it would be sent.
     * Nothing leaves the machine.
     */
    public static function preview(): void
    {
        $data = Notification::latestDigestData();
        if (!$data) {
            flash('warning', 'There are no sales on record yet, so there is '
                           . 'nothing to summarise.');
            redirect('mail');
        }
        $data['preview'] = true;

        header('Content-Type: text/html; charset=utf-8');
        echo Mailer::render('daily_income', $data);
    }

    /** Send one real takings email, to prove the setup works. */
    public static function test(): void
    {
        verify_csrf();

        $to = trim((string) input('to'));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'That does not look like an email address.');
            redirect('mail');
        }

        if (!Mailer::ready()) {
            flash('danger', 'Mail is not configured yet. Open '
                . '<code>config/mail.php</code>, fill in the sending address '
                . 'and the app password, and set <code>enabled</code> to true.');
            redirect('mail');
        }

        $data = Notification::latestDigestData();
        if (!$data) {
            flash('warning', 'There are no sales on record, so there is '
                           . 'nothing to send.');
            redirect('mail');
        }

        $ok = Mailer::send(
            $to,
            (string) Database::value(
                'SELECT full_name FROM users WHERE email = ? LIMIT 1', [$to]),
            'Takings for ' . $data['day'] . ' - '
                . "\u{20B1}" . number_format($data['revenue'], 2),
            Mailer::render('daily_income', $data));

        if ($ok) {
            Audit::log('Notifications', 'EMAIL_TEST', 'Test takings email to ' . $to);
            flash('success', 'Sent to <strong>' . e($to) . '</strong>. '
                . 'It usually lands within a few seconds — check the spam '
                . 'folder if it does not.');
        } else {
            Audit::log('Notifications', 'EMAIL_FAILED', 'Test to ' . $to
                . ' failed: ' . mb_substr(Mailer::$lastError, 0, 120));
            flash('danger', 'It would not send. The mail server said: <code>'
                . e(Mailer::$lastError) . '</code>');
        }
        redirect('mail');
    }

    /** Put an address on an account, or take one off. */
    public static function saveAddress(): void
    {
        verify_csrf();

        $id    = (int) input('user_id');
        $email = trim((string) input('email'));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'That does not look like an email address.');
            redirect('mail');
        }

        $u = Database::one('SELECT full_name, role FROM users WHERE id = ?', [$id]);
        if (!$u || !in_array($u['role'], Notification::OFFICE, true)) {
            flash('danger', 'Only an administrator or manager receives these.');
            redirect('mail');
        }

        Database::run('UPDATE users SET email = ? WHERE id = ?',
                      [$email !== '' ? $email : null, $id]);

        Audit::log('Users', 'EMAIL_SET', $email !== ''
            ? $u['full_name'] . ' will be emailed at ' . $email
            : $u['full_name'] . ' will no longer be emailed');

        flash('success', $email !== ''
            ? e($u['full_name']) . ' will now receive the takings email at '
              . '<strong>' . e($email) . '</strong>.'
            : e($u['full_name']) . ' will no longer be emailed.');
        redirect('mail');
    }
}
