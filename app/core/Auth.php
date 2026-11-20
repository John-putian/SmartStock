<?php
/**
 * FR-01 - Authentication and role-based access control.
 *
 * Four roles, from the office down to the shop floor:
 *
 *   admin    Administrator. Everything, user accounts included.
 *   manager  Store Manager. Everything except user accounts.
 *   staff    Work Staff. Receives the container, registers items, sets the
 *            list price, adjusts counts, sells at the till.
 *   cashier  Cashier. Sells at the till and watches stock come in and go out.
 *
 * Two ideas run through the rest of the system:
 *
 *   canManage()   - admin or manager. These two see Insights and Administration
 *                   and are the only people who may authorise money coming off
 *                   a price or a sale.
 *   canEditItems()- admin, manager or staff. These three may put an item into
 *                   the catalogue and price it.
 *
 * Everything the two floor roles cannot reach is blocked in the router, not
 * merely hidden in the sidebar, so typing the address by hand gets nowhere.
 */
class Auth
{
    /** Every role the system knows, most authority first. */
    public const ROLES = ['admin', 'manager', 'staff', 'cashier'];

    /** Roles that may authorise a price reduction or a discount. */
    public const APPROVERS = ['admin', 'manager'];

    public static function attempt(string $username, string $password): bool
    {
        $user = Database::one(
            'SELECT * FROM users WHERE username = ? AND status = "active" LIMIT 1',
            [$username]
        );
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'        => (int) $user['id'],
            'full_name' => $user['full_name'],
            'username'  => $user['username'],
            'role'      => $user['role'],
        ];
        Database::run('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
        Audit::log('Authentication', 'LOGIN', 'User signed in to the system');
        return true;
    }

    /**
     * Check one set of credentials WITHOUT signing anybody in, and return the
     * account only when it carries authorising rank. This is how a supervisor
     * standing at the till approves a discount: the cashier keeps the session,
     * the manager keeps the password.
     */
    public static function verifyApprover(string $username, string $password): ?array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }
        $u = Database::one(
            'SELECT id, full_name, username, role, password_hash
             FROM users WHERE username = ? AND status = "active" LIMIT 1', [$username]);

        if (!$u || !password_verify($password, $u['password_hash'])) {
            return null;
        }
        if (!in_array($u['role'], self::APPROVERS, true)) {
            return null;
        }
        unset($u['password_hash']);
        return $u;
    }

    public static function logout(): void
    {
        Audit::log('Authentication', 'LOGOUT', 'User signed out of the system');
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        if (!isset($_SESSION['user'])) return false;
        $u = Database::one('SELECT id, full_name, username, role, status FROM users WHERE id = ?', [$_SESSION['user']['id']]);
        if (!$u || $u['status'] !== 'active') {
            unset($_SESSION['user'], $_SESSION['cart']);
            return false;
        }
        unset($u['status']);
        $u['id'] = (int) $u['id'];
        $_SESSION['user'] = $u;
        return true;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function role(): string
    {
        return $_SESSION['user']['role'] ?? 'guest';
    }

    /** Strictly the Administrator account. */
    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isManager(): bool
    {
        return self::role() === 'manager';
    }

    public static function isStaff(): bool
    {
        return self::role() === 'staff';
    }

    public static function isCashier(): bool
    {
        return self::role() === 'cashier';
    }

    /** Administrator or Manager: Insights, Administration, and sign-offs. */
    public static function canManage(): bool
    {
        return in_array(self::role(), self::APPROVERS, true);
    }

    /** The people who may authorise a price drop or a discount. */
    /**
     * A discount is money off the owner's own goods, so the owner may authorise
     * it as well as a Store Manager. Staff and the till may not, whatever the
     * sidebar shows them - this is checked again in the model and once more
     * against the database at checkout.
     */
    public static function canApproveDiscount(): bool
    {
        return self::check() && self::canManage();
    }

    public static function canApprove(): bool
    {
        return self::canManage();
    }

    /**
     * Anyone signed in may put an item into the catalogue and receive stock.
     * A container is unpacked by whoever is on the floor at the time, and the
     * piece has to be listed before it can be sold - making the floor wait for
     * a manager just means items sit unlisted.
     *
     * What this does NOT give away is the money side: see canSetPrice() below.
     */
    public static function canEditItems(): bool
    {
        return self::check();
    }

    /**
     * May move the price of an item that is ALREADY listed, without asking.
     *
     * Work staff and above can put a price up on their own - they priced the
     * piece when they unpacked it. A cashier may still set the opening price on
     * an item they are adding, but once it is listed, any change they make -
     * up or down - becomes a request for an administrator or manager.
     *
     * A reduction is a separate question again: nobody below manager may make
     * one, staff included. See InventoryController::guardPriceChange().
     */
    public static function canSetPrice(): bool
    {
        return self::canManage() || self::isStaff();
    }

    /** Require a signed-in user, otherwise send to the login page. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('warning', 'Please sign in to continue.');
            redirect('login');
        }
    }

    /** Require the Administrator account. */
    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            self::refuse('That module is restricted to Administrator accounts.');
        }
    }

    /** Require Administrator or Manager. */
    public static function requireManage(): void
    {
        self::requireLogin();
        if (!self::canManage()) {
            self::refuse('That module is restricted to Administrator and Manager accounts.');
        }
    }

    /** Require an account allowed to touch inventory records. */
    /**
     * The till is the cashier's station and nobody else's.
     *
     * Selling is not a management activity, and a manager ringing up sales on
     * their own account muddies who was actually behind the counter - which is
     * the whole point of recording a cashier against every invoice.
     */
    public static function isTill(): bool
    {
        return self::isCashier();
    }

    public static function requireTill(): void
    {
        if (!self::check()) {
            self::requireLogin();
        }
        if (!self::isTill()) {
            self::refuse('The Point of Sale is the cashier\'s station. '
                . 'Your account does not ring up sales.');
        }
    }

    public static function requireStaff(): void
    {
        self::requireLogin();
        if (!self::canEditItems()) {
            self::refuse('You are not allowed to change inventory records.');
        }
    }

    /** One place for the "you may not be here" response. */
    /**
     * Turn somebody away from something they own no part of. Public so a
     * controller can enforce a rule the router cannot see - the router knows
     * roles, but not whose sale a receipt belongs to.
     */
    public static function deny(string $why): void
    {
        self::refuse($why);
    }

    private static function refuse(string $why): void
    {
        Audit::log('Security', 'DENIED',
            'Blocked access to "' . ($GLOBALS['current_page'] ?? 'unknown') . '"');
        http_response_code(403);
        // Being told "no" is only half an answer. Point at the page that
        // explains what this account *can* do, so nobody is left guessing.
        flash('danger', e($why) . ' <a href="' . url('help')
            . '" class="alert-link">See what your account can do</a>.');
        redirect('dashboard');
    }
}
