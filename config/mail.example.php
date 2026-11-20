<?php
/*
 * EXAMPLE FILE - safe to commit.
 *
 * Copy this to mail.php and put the real values in. mail.php is listed in
 * .gitignore so the real credentials stay off version control.
 *
 * The password is a Google App Password (16 letters, no spaces), generated at
 * myaccount.google.com -> Security -> App passwords. It is NOT the Gmail
 * account's own password.
 */

/**
 * Outgoing mail settings (FR-11b).
 *
 * XAMPP ships no mail transport, so mail is handed to a real SMTP server
 * instead of PHP's mail(). Gmail is the easy one to get going.
 *
 * ---------------------------------------------------------------------
 *  BEFORE THIS WORKS, TWO LINES HAVE TO BE FILLED IN BELOW
 * ---------------------------------------------------------------------
 *
 *  1. 'username'  - the Gmail address the notices are sent FROM.
 *
 *  2. 'password'  - NOT the Gmail password. Google refuses those. You need
 *                   an "App password", which is a 16-letter code Google
 *                   generates for one program:
 *
 *                     a. the account must have 2-Step Verification on
 *                        (myaccount.google.com -> Security)
 *                     b. go to myaccount.google.com/apppasswords
 *                     c. name it "Smart Stock" and click Create
 *                     d. paste the 16 letters below, spaces and all
 *
 *  An app password is a credential. Treat it like one: it belongs on this
 *  machine only, it is why `config/.htaccess` denies web access to this
 *  folder, and it should never be committed to a public repository.
 *  Revoke it from the same page the moment it is no longer needed.
 *
 *  With 'enabled' => false, or with the password left blank, nothing is
 *  ever sent and nothing breaks. The bell inside the system carries on
 *  exactly as before.
 */

return [
    // The master switch. Set to true once the two lines below are filled in.
    'enabled'    => true,

    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'encryption' => 'tls',          // 'tls' for port 587, 'ssl' for port 465

    'username'   => 'you@gmail.com',             // 1. the sending Gmail address
    'password'   => 'xxxxxxxxxxxxxxxx',             // 2. the 16-letter app password

    // What the recipient sees in the From line.
    'from_name'  => 'Smart Stock - A&A Surplus',

    /**
     * Which kinds of notice are also sent by email. Everything else stays
     * in the bell only.
     *
     * The shop asked for the end-of-day takings and nothing else: a daily
     * summary is worth an inbox, a stock warning is not - that is what the
     * bell is for, and an inbox that fills with them stops being read.
     */
    'send_types' => ['daily_income'],

    // Give up rather than hang. A page load must never wait on a mail
    // server, least of all during a demonstration.
    'timeout'    => 8,
];
