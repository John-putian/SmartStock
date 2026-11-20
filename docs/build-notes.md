# Smart Stock — A&A Surplus · Build Notes (v1.9)

Working system (PHP 8 + MySQL, runs on XAMPP).
**Installed and running on John Ray's laptop at `C:\xampp\htdocs\SmartStock`** →
<http://localhost/SmartStock/>. Double-click `START SMART STOCK.bat` in that folder to
launch Apache, MySQL and the browser in one go. Also packaged as `SmartStock.zip` for
the other proponents. The older `C:\xampp\htdocs\aa-inventory` copy is superseded and
can be deleted.

## What it is
A functional Web-Based Inventory, Sales and Decision Support System implementing the
functional requirements from the Systems Analysis Report.

Modules: Login/roles · Dashboard · Point of Sale + receipts · Inventory + categories ·
Stock Alerts · **Notifications** · **Price Approvals** · **Sales by Category** ·
Reports (sales / valuation / movement / printable) · Sales Analytics ·
User Accounts · Audit Trail.

## Change log

### v1.9 — 20 Sept 2026 · A receipt belongs to the till that issued it
Three requirements arrived under the heading "Additional Features for Staff".
Two of them the system already met; the third was a real hole, and it is the
reason for this release.

**Audit Trail for the Manager — already in place.** The `audit` route has been
`manage` since v1.5, so admin *and* manager reach it and staff and cashier do
not. Verified against the running system rather than the code: manager and
admin land on the Audit Trail, staff and cashier are turned away to the
Dashboard, and the sidebar link is on the manager's screen. Nothing was built.

**Discount processing by the Cashier — already in place, but it read wrong.**
The cashier is the only role that can raise a discount and the only role that
can apply one; a manager authorises the percentage and nothing more. The
screens did not say so plainly, so the till and the Help page were reworded:
*"You apply the discount to this sale — the manager only authorises the
amount."* The approval step stays. It is not a limit on the cashier; it is what
stops one till from writing off a container's margin on its own.

**Receipt printing — this was the hole.** `pos.receipt` was a `user` route, so
any signed-in account could open any receipt by putting its id in the address
bar, and the printed page carried no mark saying who it was for. A receipt is a
financial document handed to a customer: if four roles can print an identical
one, the invoice number stops identifying anything.

The route is now decided in the controller, because the router can only see a
role and this rule is about ownership:

* The cashier who rang the sale up opens it and prints it. Nobody else prints a
  customer receipt — not another cashier, not the staff, not the administrator.
* Admin and manager may open one, for a dispute or a refund, and their copy is
  stamped **OFFICE COPY · NOT A SALES RECEIPT** on screen and on paper.
* Every office viewing is written to the audit trail as `Sales/RECEIPT_VIEW`
  with the invoice number; every refusal is written as `Security/DENIED`.
* A receipt id that does not exist sends the cashier back to the till and the
  office back to the Sales report, instead of dropping a manager onto a till
  they are not allowed to use.

Verified end to end against stored rows, not screens: cashier prints, a second
cashier is refused, staff is refused, manager and admin get the stamped copy,
both viewings and both refusals appear in the audit trail. A warning sweep
across all four roles after the change came back clean.

Changed: `app/core/Auth.php` (a public `deny()` so a controller can enforce a
rule the router cannot see), `app/controllers/PosController.php`,
`app/views/pos/receipt.php`, `app/views/pos/index.php`,
`app/controllers/HelpController.php` and `app/views/help/index.php`.

### v1.8 — 19 Sept 2026 · The per-item demand forecast was removed
A scenario audit over the whole system (`docs/scenario-audit.md`) turned up
thirty-odd findings, and one of them was not a bug at all but a mismatch
between a feature and the business.

**The forecast answered a question this shop cannot act on.** A&A sells Japan
surplus; most pieces are unique. "Order 3 more of this armchair" is not a thing
anybody can do. The audit showed the consequences concretely: the forecast
recommended purchases for items that had never sold, ranked every one-off as
Critical because a unique piece is always at or below its reorder level, and
silently covered only the 80 best sellers - cutting exactly the out-of-stock
items the Critical count existed to surface. It also read differently on a
Tuesday than on a Sunday, because the window included the week in progress.

Removed: the `forecast` and `forecast.item` routes, `AnalyticsController::forecast()`
and `::item()`, `app/views/analytics/forecast.php`, the Demand Forecast panel and
chart on the item page, the sidebar entry, and the per-item half of
`app/models/Forecast.php` (`weeklySeries`, `forProduct`, `catalogue`).

Kept: `linearFit` and `revenueProjection`, because the Sales Analytics page still
uses them and a shop-wide revenue trend is a question the figures can honestly
answer. **Sales by Category** was always the screen the shop actually takes to
the monthly container meeting, and it stays.

Also corrected while in there: the permission matrix on the User Accounts screen
claimed every role could use the Point of Sale. The router has said cashier-only
since v1.7, and the Help page was already right - the hand-written table on the
page where roles are assigned was the one that lied.

### v1.7 — 18 Sept 2026 · The project became a repository
Tidied so it can be picked up cold, by a groupmate or by a marker, without
anyone having to explain it first.

**The root was carrying five dead one-shot scripts** — `install.php`,
`reinstall.php`, `upgrade_roles.php` (in two places) and
`upgrade_notifications.php`, all inert 410 stubs. They moved to
`tools/archive/`. `tools/` carries its own `.htaccess` with `Require all
denied`: a migration that can be triggered from a browser by anyone who guesses
the file name is a migration waiting to be abused, and the deny is the lock
rather than the obscurity of the name.

**New layout:**

```
SmartStock/
├── index.php          front controller
├── app/               core · models · controllers · views
├── assets/            css, js, images, vendored libraries
├── config/            connection settings and bootstrap
├── database/          schema.sql · seed.sql · make_seed.py
├── docs/              report · deck · run sheet · manual · build notes · screenshots
└── tools/             one-shot maintenance, denied to the web
```

**`README.md` is now a real README** — what the system is, how to run it, the
accounts, the layout, and the four rules worth knowing (router-enforced access,
price control, discount authorisation, addressed notifications). The previous
32 KB README was a full manual, not a front door; it moved to `docs/MANUAL.md`
and is linked from the new one.

**`tools/setup-database.bat`** replaces the web installer as the normal way to
set up a new machine: it pipes `schema.sql` and `seed.sql` straight through
`mysql.exe`, asks for a typed YES first, and needs nothing web-reachable. The
PHP installer is kept in `tools/` for anyone who would rather click.

**Under git**, initialised on the laptop with two commits. `.gitignore` keeps
out uploaded product photographs (they belong to one shop's stock, not to the
source), editor clutter, and `reseed.php` — a one-shot script whose run token
would be meaningless in a public repository. `config/database.php` **is**
tracked, holding the XAMPP defaults so a clone runs unedited; noted in
`docs/working-with-git.md` that this stops being acceptable the moment the
system runs anywhere real.

`docs/working-with-git.md` carries the one-time GitHub setup, the everyday
add/commit/push loop, what a groupmate does to clone and run it, and how to
read a conflict. Pushing to GitHub is John Ray's step — it needs his sign-in.


### v1.6 — 18 Sept 2026 · Notifications (FR-11)
The audit trail answers *"what happened?"*. Nothing yet answered *"what do I need to do
about it?"*, and nothing answered it **to a named person**. That is what this adds.

Client's instruction was "admin and manager and daily income", so the office is the
audience and the end-of-day takings are the headline trigger. In-app only for now;
Gmail/SMTP was deliberately deferred until after the midterm demo, because an SMTP call
that hangs would freeze a page in front of the panel.

**Who is told what.**

| Notice | Goes to | Raised when |
|---|---|---|
| Sold out | admin, manager | an item reaches zero on the floor |
| Running low | admin, manager | an item falls to or below its reorder level |
| Price request | admin, manager | somebody asks to move a listed price |
| Price decision | the person who asked | an approver rules on their request |
| Discount authorised | admin, manager | money comes off a sale, naming who allowed it |
| Daily income | admin, manager | first page load of a new day, for yesterday |

A cashier receives nothing. They ring up sales; deciding is not their job, and a bell
that cries wolf is a bell nobody reads.

**Where it appears.** A bell in the top bar of every page carries *this account's*
unread count — not a store-wide figure, so two people at two tills each see their own.
The dropdown shows the latest six; "Notifications" in the sidebar under Operations opens
the full centre, filterable by read state and by kind, with "mark all as read".

**Two rules hold everywhere in `Notification`:**

1. **Raising a notice must never break the thing that raised it.** Every write is
   wrapped and failures are swallowed on purpose. A sale that has committed must not be
   rolled back because a notice could not be stored.
2. **A repeat event updates its own notice instead of stacking.** An item that dips
   below its reorder level twice in a day is one notice, not two — that is what the
   `dedupe_key` unique index is for. When an item is restocked back into health, its
   `stock_low` and `stock_out` notices are deleted rather than left to rot.

**No scheduler on XAMPP**, so the daily takings digest is produced lazily: the first
signed-in page load of a new day writes yesterday's summary if it is missing, keyed by
date so it can only ever be written once. Costs one indexed lookup thereafter. A day
with no sales gets no digest.

**New:** `notifications` table · `app/models/Notification.php` ·
`app/controllers/NotificationController.php` · `app/views/notifications/index.php` ·
three routes at `user` level (`notifications`, `.open`, `.read`).

**Two real bugs found while building this.**

1. **`Sale::checkout()` could report a successful sale as failed.** The discount notice
   was being raised inside the try block that wraps the transaction. The transaction had
   already committed, so a throw from the notification would fall into the catch and
   call `Database::rollback()` on a finished transaction — returning `ok: false` for a
   sale that had actually gone through, which at a till means ringing it up twice. The
   post-commit block now sits in a try/catch of its own.
2. **The peso sign was being shown literally.** `peso()` returns the HTML entity
   `&#8369;`, which is right for a template but wrong inside a notice: a notice is stored
   as plain text and escaped again on the way out, so the entity would have appeared on
   screen as characters. Notices now store the real UTF-8 sign, which passes through
   `htmlspecialchars()` untouched. The seed had the matching problem, plus a subtler one:
   the digest title spelled out a weekday computed at *generation* time while every row
   around it is placed relative to `CURDATE()` at *import* time, so the title disagreed
   with its own timestamp the moment the file was imported on a different day. The title
   and its dedupe key are now built in SQL from `CURDATE()`.

**Upgrading an existing installation.** `upgrade_notifications.php` sits at the project
root (`database/.htaccess` denies web access, so anything meant to be opened in a
browser cannot live in `database/`). It is idempotent and non-destructive: it creates
the table only if absent, then *primes the bell* by running the real triggers over the
stock currently on the shelves and the price requests currently pending, so the bell has
something truthful in it the moment it is first opened — no reseed, no data loss.
Token-protected, and replaced with a disabled 410 stub once run.

### v1.4 — 11 Sept 2026 · Four roles, price control and discount authorisation
The client wants the shop floor separated from the office, and wants money to stop
leaking through unauthorised mark-downs. Two roles became four.

| Role | Sees | May do |
|---|---|---|
| **admin** — Administrator | Everything | Everything, user accounts included |
| **manager** — Store Manager | Everything except User Accounts | Authorises price changes and discounts |
| **staff** — Work Staff | Operations only | Receives stock, adds and prices items, may *raise* a listed price. Lowering needs approval |
| **cashier** — Cashier | Operations only | Sells at the till, adds and prices items. Changing a *listed* price needs approval, either direction |

### v1.5 amendment — the catalogue is open to the whole floor
Both the cashier and the work staff add products (client's instruction: *"for adding
products is for staff and cashier"*). A container is unpacked by whoever is on the floor
at the time and the piece has to be listed before it can be sold, so making the floor
wait for a manager just leaves items unlisted. `Auth::canEditItems()` is now true for
every signed-in account, and Add Item, Edit Item and Receive/Adjust Stock went from the
`staff` level to `user`.

What the two floor roles still do *not* share is the money side, and that is where the
separation now lives — `Auth::canSetPrice()`:

- **Anyone** may set the opening price of an item they are *adding*. Somebody has to,
  and nothing is being marked down.
- **Work staff and above** may *raise* a price already on the shelf without asking.
- **A cashier** may not move a listed price at all. Any change they make, up or down,
  becomes a request.
- **Nobody below manager** may *lower* a listed price, work staff included.

So a cashier's request can now go either way, and the approvals screen shows a green
"+₱x / up" as readily as a red "−₱x / off". Archiving an item is still manager and above
— it is the one destructive action on the catalogue.

**Cashier and work staff can no longer reach Insights at all.** Reports, Sales by
Category and Sales Analytics moved from `user`/`admin` to a new
`manage` access level, and the router turns a floor account away even if the address is
typed by hand — the sidebar entry disappearing is the courtesy, not the lock. Every
refusal writes a `Security / DENIED` line into the audit trail naming the page.

**New access levels in `index.php`:** `guest` · `any` · `user` · `staff` · `manage` ·
`admin`, matched by `Auth::requireStaff()` / `requireManage()` / `requireAdmin()`.
`Auth::canManage()`, `canApprove()` and `canEditItems()` are the three predicates the
views use; `Auth::isAdmin()` now means *strictly* the administrator.

**Price control.** Work staff price the goods they unpack — they are holding the piece —
and may put a price up on their own. A *reduction* is where money quietly leaks, so it
is written to a new `price_requests` table and the item keeps its old price until an
administrator or manager rules on it. The rest of the edit saves normally; only the
price is held back. The edit form reveals a "why are you lowering it?" box the moment
the figure drops below the listed price, and the approver reads that before deciding.
Approving writes the new price through `Product::setPrice()`; rejecting closes the
request and leaves the price alone. Both outcomes are stamped with who decided, when,
and their note. New screen: **Price Approvals** (approvers see the queue; work staff see
only their own asks, read-only, under Operations as "My Price Requests").

**Discount authorisation.** A discount is money off the owner's goods, so it is not the
cashier's to give. Typing any amount at the till reveals an authorisation panel asking
an administrator or manager to enter *their own* sign-in — the cashier keeps the
session, the supervisor keeps the password. `Auth::verifyApprover()` checks credentials
without signing anybody in and rejects any account below manager rank. The approver's id
is stored on `sales.discount_approved_by`, printed on the receipt, shown in the sales
report and carried in the CSV export as "Discount Approved By". `Sale::checkout()`
re-checks it independently, so a discount cannot reach the database by any other route.
Failed attempts log `Sales / DISCOUNT_DENIED`.

**Shop-floor dashboard.** Cashiers and work staff get `dashboard/floor.php` instead of
the manager's dashboard — no month takings, no growth rate, no category mix. It answers
the two questions the floor has: what came in, what went out. My sales today · pieces
out today · pieces on the floor · running low, then a live stock-movement feed and the
low-stock list.

**Upgrading an existing installation.** `database/upgrade_roles.php` is an idempotent
migration, not a re-install: it widens the role enum, adds the column and the table,
promotes the `manager` account, creates a work-staff account and back-fills historical
discounts to the manager on record. Products, sales and audit history are untouched. It
was run once on the laptop through a one-time token and then overwritten with a disabled
410 stub — nothing executable is left in `htdocs`.

**One real bug found while doing this.** `layouts/header.php` looped the flash messages
into `$f`, and because it runs inside `view()` immediately before the page template, it
silently overwrote the `$f` filter array that the Audit Trail (and others) rely on. It
only showed when a flash message was actually present — which the new blocked-route
redirects made common. Renamed to `$flashMsg` and unset after the loop.

### v1.3 — 11 Sept 2026 · Watchlist withdrawn, Sales by Category added
The client does not want the Customer Watchlist. What they actually need is daily sales
**by category**, because the monthly meeting with the surplus shipment owner can only
ever be a conversation about categories — a container holds whatever it holds, so no
specific product can be requested.

**Removed:** the Watchlist module in full — routes, sidebar entry, controller, model,
view, the `watchlists` table, its seed data and its row in the user-permission matrix.
The three PHP files on the laptop were overwritten with a "withdrawn" stub rather than
deleted (the deployment bridge cannot delete); they are unreachable and safe to remove
by hand.

**Added:** `reports.category` → `ReportController::category()` +
`app/views/reports/category.php`, backed by two new queries in the Sale model,
`dailyByCategory()` and `categoryPerformance()`. The screen carries:
- four headline figures (revenue, pieces sold, best category, categories to restock);
- **"What to Ask For in the Next Container"** — the request list, most urgent first,
  each category with a plain-English reason and a *suggested quantity*:
  `ceil(pieces per day × 30) − pieces still on the floor`;
- a stacked daily chart and a day-by-day matrix (dates down, categories across,
  peso takings with pieces underneath, row and column totals);
- a scorecard with share of takings, average price achieved, sell-through
  ("how much of what we had actually moved") and days of cover.
- CSV export (`what=category`) and a print-clean layout for the meeting.

Categories that sold nothing are deliberately still listed — "we shifted none of these"
is exactly what the owner needs to hear before the next container is loaded.

**Two real bugs found and fixed:**
1. **Seed generator restock was dead code.** In `make_seed.py` the twice-monthly
   container restock had `stock[...] += add` and the movement record sitting *after* a
   `continue`, so they never ran. Consequence: every non-furniture category sold out in
   January and was never replenished. Fixed.
2. **`export_csv()` corrupted every CSV on PHP 8.4.** `fputcsv()` without the `$escape`
   argument is deprecated in 8.4 and the notice was written into the downloaded file.
   Now passes `',', '"', ''` explicitly. Affected all four exports.

Also tightened `nav_item()` so a sub-page with its own sidebar entry no longer
highlights two items at once.

## Branding
Themed to the A&A Surplus badge: hinomaru red `#c41114` (sampled from the logo),
sumi black `#101010`, warm washi paper background. Logo appears in the sidebar,
sign-in screen, receipts, QR price labels, printed reports and the favicon.
System name **Smart Stock**; store **A&A Surplus**.

## Catalogue — the store's REAL stock
Built from 32 photographs supplied by the client (`C:\Users\JohnRay\Downloads\A&A`):
24 furniture pieces, 3 kitchenware/dining sets, 3 home-decor pieces, 1 guitar,
1 tennis racket set. Two categories were added for the last two
(Musical Instruments, Sports & Leisure); Major Appliances, Kitchen Appliances,
Electronics & Audio and Home Accessories exist but are empty pending stock.

Prices are set against observed second-hand Japan-surplus rates in the Philippines
(used.com.ph, Carousell PH — Sept 2026), NOT new-item retail. Examples: dining table
set ₱8,500 · chest of drawers ₱4,200 · swivel office chair ₱1,900 · plate set ₱650.

**Photos:** because second-hand stock is one-of-a-kind, `products.image_path` holds a
photograph of the *actual unit*, shown on the POS tile (with stock status and condition
class badges), the inventory list and the item page. The Add/Edit Item form has a
photo upload; uploads are validated via `getimagesize()` and stored under a
server-generated name, so a script cannot be uploaded disguised as an image.

## Demo accounts
`admin` / admin123 · `manager` / manager123 · `staff` / staff123 ·
`cashier` / cashier123 · `cashier2` / cashier123.
Sign in as **staff** to see the price-request flow, and as **cashier** to see the till
ask for a supervisor before it will take a discount. **Change these before real use.**

## Setup on a new machine
1. Copy `SmartStock` into `C:\xampp\htdocs`.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open <http://localhost/SmartStock/install.php> and click Install.
4. **Overwrite `install.php` with the disabled stub, or delete it.**
5. Sign in as `admin` / `admin123`.

## Stack decisions
- **Plain PHP 8 in an MVC layout**, not Laravel — Laravel needs Composer and
  `artisan serve` and won't drop into `htdocs`. Structure still demonstrates MVC.
  If the panel insists on Laravel, this is the one thing to change.
- Front controller `index.php?page=<route>` — no mod_rewrite dependency.
- Bootstrap 5, Bootstrap Icons, Chart.js and a QR generator bundled locally, so the
  system runs offline during the defence.
- Seed timestamps use `DATE_SUB(CURDATE(), INTERVAL n DAY)` so the sample history
  always ends "today" whenever it is imported. Any date *spelled out* in seeded text
  has to be built in SQL from `CURDATE()` for the same reason.
- `database/` carries its own `.htaccess` with `Require all denied`, so the SQL files
  and the migration script cannot be fetched over the web. (A migration meant to be run
  through the browser therefore has to sit at the application root, not in `database/`.)
- No scheduler exists on a XAMPP install, so anything "daily" is produced lazily on the
  first page load of a new day and deduped by date, rather than by cron.

## Why there is no per-item demand forecast (FR-09), for defence questions

There was one: twelve weeks of weekly demand per item, a four-week moving
average blended with a least-squares trend at 60/40, converted into days of
cover and a suggested reorder quantity.

It was removed on 19 September 2026, and the reason is the business rather than
the code. **A&A sells Japan surplus. Most of the stock is one of a kind.** You
cannot reorder a particular armchair, so a screen whose output is "order 3 more
of this" is answering a question the shop can never act on. The audit made the
mismatch concrete: the forecast was recommending purchases for items that had
never sold, and ranking unique pieces as Critical because a one-off is always at
or below its reorder level.

What replaced it was already there and is the thing the shop actually uses:
**Sales by Category**, a flat 30-day cover at the current rate, per category.
A container holds whatever it holds, so the monthly meeting with the shipment
owner can only ever be a conversation about categories. That report is
deliberately simpler than the forecast was, because it has to survive being read
aloud in that meeting.

The revenue projection on the Sales Analytics page stayed - it is about the shop
as a whole, not about reordering a specific piece, so it is a question the
figures can honestly answer.

## Security measures
bcrypt password hashing · PDO prepared statements throughout · output escaping ·
CSRF tokens on state-changing forms · **role checks enforced in the router, not just
hidden menu links**, with every refusal logged · price reductions and discounts require
an administrator or manager and are checked twice (controller and model) · checkout
wrapped in a DB transaction with `SELECT ... FOR UPDATE` so stock cannot be oversold ·
image uploads validated by content, not filename · a notification belongs to one account
and the account id comes from the session, never from the request, so one person cannot
open or clear another's · installer and migration scripts overwritten with disabled 410
stubs the moment they have run.

## Verified
PHP lint clean across all source files. Every page rendered as all four roles — 139 page
loads in total, including deliberately malformed query strings — with zero PHP warnings,
notices or deprecations.

Role gate, by route: cashier and staff are refused Reports, Sales by Category, Sales
Analytics, Categories, Users and Audit; cashier is additionally refused
Add/Edit Item and Price Approvals; manager is refused only User Accounts; admin reaches
everything. Sidebars confirmed to match exactly.

Price rules, one product per case, checked against the products table afterwards:

| Action | Result |
|---|---|
| cashier raises a listed price | held, request filed |
| cashier lowers a listed price | held, request filed |
| staff raises a listed price | applied immediately, no request |
| staff lowers a listed price | held, request filed |
| manager lowers a listed price | applied immediately |
| admin raises a listed price | applied immediately |

A cashier adding a brand-new item set its opening price of ₱850 with **no** request
filed, and its two unit barcodes were generated; the same cashier then received three
more pieces, taking the count to five. Staff POSTing `approvals.decide` on their own
request was refused and the request stayed pending; the manager's approval applied the
new price and stamped the decision, for a raise as well as a drop.

Discount flow, as cashier with a ₱100 discount: no authorisation, a wrong password, a
work-staff account and a second cashier account each refused the sale outright (sale
count unchanged, `DISCOUNT_DENIED` logged); real manager credentials recorded the sale
with `discount_approved_by = 2` and a `DISCOUNT` audit line. Calling `Sale::checkout()`
directly with no approver, and with a cashier named as approver, both threw — the model
lock holds independently of the controller.

Notifications, checked against the stored rows rather than the screen: the bell count
matches each account's own unread total (admin 7, manager 7, staff 0, cashier 0) and the
centre opens for all four roles. Staff filing a price reduction raised exactly two
notices, one per approver; the manager's decision raised exactly one, addressed to the
person who asked. A cashier's sale with a manager-authorised discount raised one notice
per approver naming both the till and the authoriser, and the sale itself committed
normally. A cashier opening another account's notice by id was refused and the notice
stayed unread. "Mark all as read" cleared that account's notices and left the other
account's untouched. The migration was then run against a database with the table
dropped: created it, primed 6 stock warnings, 2 pending price requests and yesterday's
digest; run a second time it correctly reported "already here" and the notice count did
not move. An invalid token shows the confirmation form instead of running.

All four CSV exports clean; the sales export now carries "Discount Approved By".
The roles migration was verified on a database rolled back to the old shape: applied
cleanly, then ran again with every step correctly reporting "already in place". Applied
on the laptop (255 historical discounted sales back-filled), then the script was
disabled and the 410 stub confirmed.

## Known follow-ups
- **Gmail / SMTP notifications** — deliberately deferred until after the midterm demo.
  An SMTP call that hangs would freeze a page in front of the panel, and XAMPP has no
  mail transport configured. The trigger points already exist, so adding it is a matter
  of calling a mailer beside each `Notification::` call, ideally from a queue rather
  than inline.
- Vision and mission statements still blank in the analysis report — needs client
  validation, not a system change.
- Demo passwords (admin123 etc.) must be changed before real use.
- **Open question for the client:** should work staff be able to give a discount with a
  supervisor's sign-off (they can today, same as a cashier), or should the till refuse
  discounts from staff accounts entirely?
- **Open question:** should a rejected price request be re-submittable, or locked for a
  period? Today the staff member can simply ask again.
- **Open question:** should a notice expire? Today a read notice is kept indefinitely,
  which is fine for a demo but will need a retention rule in real use.
- Item photos are centre-cropped square for uniform tiles; a few trim the A&A
  backdrop. Switch to letterbox if the client prefers the full frame.
- Appliance categories are empty — add stock with photos as containers arrive.
- The 30-day cover window in the category suggestion is a constant
  (`ReportController::COVER_DAYS`). If containers start arriving fortnightly, change it.
- Still undecided from earlier: whether the one-off (single-piece) rule should extend
  beyond Furniture to Home Decor, Musical Instruments and Sports & Leisure. (The other
  half of that question - hiding the forecast's reorder quantity for one-off items -
  answered itself when the forecast was removed.)
- The three withdrawn Watchlist files on the laptop are stubs, not deletions, and can be
  removed by hand.
