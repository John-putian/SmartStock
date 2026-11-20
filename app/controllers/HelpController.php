<?php
/**
 * Help - what can I do, and how do I do it?
 *
 * The permission table on this page is not written by hand. It is derived from
 * the router's own route table, so what a person is told they may do is what
 * the system will actually let them do. A hand-written table would be a
 * promise; this is a description.
 */
class HelpController
{
    /**
     * Which roles a given access level admits.
     *
     * This mirrors the switch in index.php. It is the one piece of knowledge
     * repeated here, so it is kept tiny and next to the thing it mirrors.
     */
    private const ADMITS = [
        'any'    => ['admin', 'manager', 'staff', 'cashier'],
        'user'   => ['admin', 'manager', 'staff', 'cashier'],
        'staff'  => ['admin', 'manager', 'staff', 'cashier'],  // canEditItems()
        'till'   => ['cashier'],
        'manage' => ['admin', 'manager'],
        'manager' => ['manager'],
        'admin'  => ['admin'],
        'guest'  => [],
    ];

    /**
     * Plain-English names for the pages worth listing. A route that is a form
     * target or a redirect is deliberately absent - it is not a thing anybody
     * "goes to", and listing it would only add noise.
     */
    private const PAGES = [
        'dashboard'        => ['Operations',     'See the dashboard'],
        'pos'              => ['Operations',     'Sell at the till'],
        'inventory'        => ['Operations',     'Browse the stock list'],
        'inventory.create' => ['Operations',     'Add a new item'],
        'inventory.edit'   => ['Operations',     'Edit an item'],
        'inventory.stockin'=> ['Operations',     'Receive stock from a container'],
        'inventory.archive'=> ['Operations',     'Archive an item'],
        'alerts'           => ['Operations',     'See what is running out'],
        'notifications'    => ['Operations',     'Read your notifications'],
        'approvals'        => ['Operations',     'See price and discount requests'],
        'reports'          => ['Insights',       'Read the sales reports'],
        'reports.category' => ['Insights',       'See sales by category'],
        'analytics'        => ['Insights',       'See the sales analytics'],
        'categories'       => ['Administration', 'Manage categories'],
        'users'            => ['Administration', 'Manage user accounts'],
        'mail'             => ['Administration', 'Set up email notifications'],
        'audit'            => ['Administration', 'Read the audit trail'],
    ];

    /**
     * Things that are not a page of their own - a permission rather than a
     * place. These are described here because the router cannot show them.
     */
    private const POWERS = [
        ['Set the price of an item you are adding',
         ['admin', 'manager', 'staff', 'cashier'], ''],
        ['Put up a price already on the shelf',
         ['admin', 'manager', 'staff'], 'A cashier has to ask.'],
        ['Bring down a price already on the shelf',
         ['admin', 'manager'], 'Anyone else files a request and waits.'],
        ['Ask for a discount at the till',
         ['cashier'], 'The sale is held until it is answered.'],
        ['Approve or refuse a discount',
         ['admin', 'manager'], 'The administrator or a Store Manager approves discounts.'],
        ['Approve or refuse a price change',
         ['admin', 'manager'], ''],
        ['Apply the discount and print the receipt',
         ['cashier'], 'The office may view a receipt, stamped as a copy.'],
        ['Reprint a receipt for a customer',
         ['cashier'], 'Only the till that issued it.'],
    ];

    public static function index(): void
    {
        $routes = (array) ($GLOBALS['routes'] ?? []);
        $roles  = ['cashier', 'staff', 'manager', 'admin'];

        // Build the table from the live route table.
        $matrix = [];
        foreach (self::PAGES as $page => [$group, $label]) {
            if (!isset($routes[$page])) {
                continue;                       // route withdrawn: drop the row
            }
            $access  = $routes[$page][2] ?? 'user';
            $admits  = self::ADMITS[$access] ?? [];
            $matrix[$group][] = [
                'label' => $label,
                'who'   => array_fill_keys($roles, false) + [],
                'roles' => $admits,
            ];
        }

        view('help/index', [
            'matrix' => $matrix,
            'powers' => self::POWERS,
            'roles'  => $roles,
            'me'     => Auth::role(),
        ], 'Help');
    }

    /** Is this one of the addresses a home or shop router hands out? */
    private static function isLan(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        [$a, $b] = array_map('intval', explode('.', $ip) + [1 => 0]);
        return $a === 10
            || ($a === 192 && $b === 168)
            || ($a === 172 && $b >= 16 && $b <= 31);
    }

    /**
     * "Open on my phone" - the address to type, and the QR to scan.
     *
     * The laptop's address on the shop Wi-Fi is handed out by the router and
     * changes when the network changes, which is why a printed address stops
     * working. This page asks the machine for its current address every time it
     * is opened, so it is right by construction instead of being right on the
     * day somebody wrote it down.
     */
    public static function connect(): void
    {
        $host = php_uname('n');
        $found = [];
        foreach ((array) (gethostbynamel($host) ?: []) as $ip) {
            if (self::isLan((string) $ip)) {
                $found[(string) $ip] = true;
            }
        }
        // The address this very request arrived on. If somebody is already
        // reading this page over the Wi-Fi, that address is proven to work.
        $arrivedOn = (string) ($_SERVER['SERVER_ADDR'] ?? '');
        $provenByThisRequest = self::isLan($arrivedOn);
        if ($provenByThisRequest) {
            $found[$arrivedOn] = true;
        }

        $port   = (int) ($_SERVER['SERVER_PORT'] ?? 80);
        $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $folder = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

        /**
         * A development laptop usually has more than one address, and most of
         * them are no use to a phone: VirtualBox, WSL and Hyper-V each invent
         * a private network of their own that exists only inside this machine.
         * Those are kept in the list - the guess could be wrong - but they are
         * pushed to the bottom and labelled, so the real Wi-Fi address is the
         * one offered first.
         */
        $virtual = static function (string $ip): ?string {
            if (strpos($ip, '192.168.56.') === 0 || strpos($ip, '192.168.99.') === 0) {
                return 'VirtualBox';
            }
            if (strpos($ip, '172.') === 0) {
                return 'WSL, Docker or Hyper-V';
            }
            return null;
        };

        $addresses = [];
        foreach (array_keys($found) as $ip) {
            $addresses[] = [
                'ip'      => $ip,
                'url'     => ($https ? 'https://' : 'http://') . $ip
                             . (($port === 80 && !$https) || ($port === 443 && $https) ? '' : ':' . $port)
                             . $folder . '/',
                'proven'  => $ip === $arrivedOn && $provenByThisRequest,
                'virtual' => $virtual($ip),
            ];
        }
        // Proven first, then real network adapters, then the virtual ones.
        usort($addresses, static fn(array $a, array $b): int
            => [$b['proven'], $a['virtual'] !== null] <=> [$a['proven'], $b['virtual'] !== null]);

        // Which interfaces Apache is bound to. Bound to 127.0.0.1 alone, no
        // phone can ever reach it however the firewall is set, so it is worth
        // saying out loud rather than leaving him to guess.
        $listen = [];
        foreach (['C:/xampp/apache/conf/httpd.conf', '/etc/apache2/ports.conf'] as $conf) {
            if (!is_readable($conf)) {
                continue;
            }
            foreach (preg_split('/\R/', (string) file_get_contents($conf)) as $line) {
                if (preg_match('/^\s*Listen\s+(\S+)/i', $line, $m)) {
                    $listen[] = $m[1];
                }
            }
            break;
        }
        $boundToLocalOnly = $listen !== [] && !array_filter($listen,
            static fn(string $l): bool => strpos($l, '127.0.0.1') === false && strpos($l, '[::1]') === false);

        view('help/connect', [
            'host'      => $host,
            'addresses' => $addresses,
            'arrivedOn' => $arrivedOn,
            'proven'    => $provenByThisRequest,
            'https'     => $https,
            'listen'    => $listen,
            'localOnly' => $boundToLocalOnly,
            'folder'    => $folder,
        ], 'Open on a Phone');
    }
}
