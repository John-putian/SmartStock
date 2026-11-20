# Smart Stock — corrected source release

Reviewed and repaired October 9, 2026. Requires PHP 8.2+ and XAMPP MariaDB. Interface libraries are included locally.

## Advanced Database Systems: normalization (UNF to 3NF)

Applied to Smart Stock's real sales data in MariaDB. Full write-up: ADVANCED_DATABASE_IMPLEMENTATION.md.

- database/ads/normalization_demo.sql builds a separate aa_normalization_demo database from real aa_inventory sales: UNF, 1NF, 2NF and 3NF tables with primary and foreign keys. It only reads aa_inventory.
- database/ads/normalization_proof.sql has seven proofs: repeating groups, atomic rows, redundancy, row counts, lossless join (0 missing / 0 invented), referential integrity (error 1452) and the live foreign keys.
- database/ads/normalization_proof_results.txt is the actual output from the run on 20 Nov 2026.
- normalization-explorer.php shows every stage in the browser (localhost only, read-only): http://localhost/SmartStock/normalization-explorer.php
- database/ads/VOICE-OVER-SCRIPT.md is the narration of the video.

Video: (YouTube link)

## Existing installation

Back up the database and application first. Replace application files, preserving your own config/database.php and config/mail.php settings. If the database already has invoice_counters, sales.idempotency_key, sale_items.category_at_sale, sale_items.discount_share and discount_requests.cart_fingerprint, no schema import is needed. Otherwise apply database/migrations/2026_10_02_phase1_correctness.sql after backing up. This additive migration targets MariaDB.

Never import schema.sql, seed.sql or the snapshot into an existing store database: those files replace data. Test this release against a backup copy before switching the store.

## New demonstration installation

1. Extract or clone the SmartStock folder into C:\xampp\htdocs.
2. Copy config/database.example.php to config/database.php (and the other *.example.php files if you need email or photo suggestions).
3. Start Apache and MySQL in XAMPP.
4. Run SETUP-DATABASE.cmd. It imports database/schema.sql and the sample database/seed.sql into a new aa_inventory database and refuses to run when aa_inventory already exists. It assumes XAMPP at C:\xampp and a local root account with no password.
5. Open http://localhost/SmartStock/.

The full snapshot of the store's live database is not in this repository: it holds real sales history. install.php is disabled, not an installer.

Demo accounts: admin/admin123, manager/manager123, staff/staff123, cashier/cashier123, cashier2/cashier123. Change these before real use. SMARTSTOCK_DEMO=1 shows the demo-password table on the login page; it is hidden by default.

## Roles and flows

- Administrator: inventory, approvals, reports, analytics, categories, audit, accounts and email settings.
- Manager: management modules except accounts/email settings.
- Staff: stock, inventory, personal requests, notifications and help.
- Cashier: floor operations plus POS and their own receipts.

Only cashiers use POS. Anyone signed in may register stock and set its opening price. Staff may increase a listed price; cashier price changes and staff reductions require approval. Only Store Managers authorize discounts against the exact units and prices requested. If the cart changes, withdraw/cancel the old request and ask again.

Receive stock adds a positive quantity. Physical count correction sets the exact quantity, including zero. Checkout commits the sale, units, ledger and consumed discount together.

## Reporting

Item/category revenue is net of discounts. New sales preserve category and cost at sale. Older lines without snapshots show Not captured (legacy); their discounts are allocated proportionally from sale headers. Historical costs cannot be reconstructed from today's prices. Legacy/unmatched categories do not produce restock suggestions. Captured category names are matched by name, so renamed/deleted categories appear as history only.

## Configuration and remaining checks

Database settings are in config/database.php. Optional SMARTSTOCK_DB_PORT and SMARTSTOCK_DB_NAME override port/name for isolated testing. Defaults are 3306 and aa_inventory. SMARTSTOCK_DEBUG=1 enables detailed local errors; default is off. Session cookies use HttpOnly, SameSite=Lax and Secure when PHP detects HTTPS.

Apache must allow .htaccess to protect source/config/data directories. The PHP development server does not enforce those files; do not expose it publicly. Email stays disabled until the owner's SMTP credentials are configured and delivery is tested. Test scanner, printers and paper sizes on actual store devices.

See AUDIT-REPORT.md for verification and limits. docs/build-notes.md records historical project context and may describe older behavior.

## Optional photo suggestions

Names, categories and descriptions can be drafted from a selected product photo. Initial selling-price suggestions use similar local sales after discounts, or a clearly labeled ₱500 demo placeholder when no matches exist. Users explicitly apply and edit suggestions before saving. Local price suggestions work without an AI key. Read PHOTO-SUGGESTIONS.md to configure photo generation and disable demo pricing for real use. The shipped copy contains no AI key.

