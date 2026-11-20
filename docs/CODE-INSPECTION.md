# Smart Stock — full code inspection

A&A Surplus · Web-Based Inventory, Sales and Decision Support System
IT 307 — Web Systems and Technologies 2 · Duque · Dizon · Puti-an

Inspected 6 November 2026. Every source file was read: 71 PHP files, the
schema, the migration, the client-side JavaScript and the Apache config.
Vendored libraries (PHPMailer, Bootstrap, Chart.js, qrcode.js) were identified
but not audited — they are third-party.

~12,000 lines of hand-written source, plus ~13,000 lines of seed data.

---

## 1. What it is

A front-controller MVC application in plain PHP 8 with PDO, no framework and
no Composer. Every request enters `index.php`, is matched against a route
table, has its access level enforced, and is dispatched to a controller which
renders a view.

```
index.php                  front controller: 54 routes, access + CSRF gates
config/                    bootstrap, database, mail, photo-AI settings
app/core/      7 classes   Database, Auth, Audit, Helpers, Xlsx, Mailer, PhotoAssistant
app/models/   11 classes   Product, ProductUnit, Sale, DiscountRequest, PriceRequest,
                           Notification, Forecast, User, Category, PriceSuggestion
app/controllers/ 14        one per module
app/views/    30 files     layouts, one folder per module
assets/                    style.css, 4 app scripts, vendored Bootstrap/Chart.js/qrcode
database/                  schema.sql, seed.sql, one dated migration
```

**Twelve tables.** `users`, `categories`, `products`, `product_units`, `sales`,
`sale_items`, `stock_movements`, `price_requests`, `discount_requests`,
`notifications`, `audit_logs`, `settings`, plus `invoice_counters` from the
migration. InnoDB throughout, utf8mb4, foreign keys on every relationship with
deliberate `ON DELETE` choices (`CASCADE` for owned rows, `SET NULL` where
history must survive the parent).

---

## 2. The design decision the whole system rests on

Most inventory systems store a quantity. This one stores **each physical
piece**, in `product_units`, with its own barcode:

```
AA-FUR-0007-03
│  │   │    └── unit number within the item
│  │   └─────── item number
│  └─────────── category code
└────────────── store prefix
```

That is the correct answer for second-hand goods and it is what makes the rest
coherent. A one-off table has one unit; five matching chairs have five, each
separately graded (Class A/B/C/For Parts), each with its own remarks, each sold
individually. `products.quantity` is not authoritative — it is kept in step by
`ProductUnit::syncQuantity()`, which counts the units actually on the floor.

The cart at the till therefore holds *unit ids*, not product ids. The system
always knows exactly which chair went out of the door.

---

## 3. The strongest code in the project

### 3.1 `Sale::checkout()` — the transaction

This is the best-engineered function in the codebase. Inside one transaction it:

- takes `FOR UPDATE` locks on parent products **in sorted id order**, which is
  the standard defence against two tills deadlocking on the same two items;
- locks and re-reads each unit, refusing anything already sold;
- validates the discount approval, locks its row, checks expiry, checks the
  approver is *still* an active manager, and **spends it inside the same
  transaction**;
- allocates the discount across lines by largest-remainder so the per-line
  shares sum to the total exactly, to the centavo;
- writes the header, the lines, the unit retirements, the movement ledger and
  the audit entries;
- commits, and only then raises notifications — wrapped in `try/catch` so a
  failed notice can never roll back a completed sale.

### 3.2 Three race conditions that were found and actually fixed

The migration `2026_10_02_phase1_correctness.sql` documents them, and the code
matches:

| Race | Old behaviour | Fix |
|---|---|---|
| Invoice numbers | `COUNT(*) + 1` — two tills got the same number; deleting a sale made the counter walk backwards onto a number already printed | `invoice_counters` bumped with MySQL's `LAST_INSERT_ID(expr)` trick: the update and the read are one atomic statement |
| Discount reuse | The approval was marked used *after* commit, so two concurrent checkouts both claimed it | Locked with `FOR UPDATE` and spent inside the transaction, with a verification read afterwards |
| Double-click | Stopped only as a side effect of the unit lock, and reported a confusing "already sold" | `idempotency_key` with a unique index; the second request returns the first one's sale |

Finding and fixing these is well beyond what a course project normally shows.

### 3.3 The discount cart fingerprint

`DiscountRequest::fingerprint()` hashes every unit id and its price. An
approval is bound to that hash. Previously an approval belonged to the
*cashier*, so a 10% sign-off on an ₱850 cart could be spent on a ₱50,000 one.
Now, if the cart changes at all, the hash changes and the approval stops
matching — and the message says so plainly rather than silently charging full
price or the old discount.

### 3.4 `Xlsx.php` — a dependency-free .xlsx writer

371 lines that build a ZIP container by hand with `pack()`, because the `zip`
extension is not enabled in this XAMPP install and PhpSpreadsheet would need
Composer. It writes real column widths, number formats, frozen panes and an
autofilter. The header comment explains exactly why it exists: CSV exports were
opening in Excel with dates as `########`.

### 3.5 Historical snapshots on `sale_items`

`category_at_sale` and `cost_price_at_sale` are captured at the moment of sale.
Before this, reports resolved the category live through `products`, so
re-categorising an item **rewrote last month's report**. The columns are NULL
for older rows and the queries say "Not captured (legacy)" rather than
borrowing today's values. That is the honest choice, and it is commented as
such.

### 3.6 Access control enforced at the router

54 routes each carry an access level (`guest/any/user/till/staff/manage/
manager/admin`), checked in `index.php` *before* the controller runs. A cashier
typing `?page=analytics` is refused and the refusal is written to the audit
trail as `DENIED`. The Help screen builds its permission table from the same
route array, so the documentation cannot drift from what is enforced.

`PosController::receipt()` adds an ownership check the router cannot express —
a receipt belongs to the till that issued it; the office may view one but it is
stamped as a copy and the view is logged.

---

## 4. Security review

| Check | Result |
|---|---|
| SQL injection | **Clean.** Every query uses PDO prepared statements with bound parameters. No string interpolation of user input anywhere in `app/`. The only dynamic SQL fragments are `implode(',', array_fill(...,'?'))` placeholder lists and `(int)`-cast LIMIT values. |
| Output escaping | **Clean.** `e()` wraps output throughout. The raw `<?=` echoes found are all literal ternaries producing CSS class names or loop counters. |
| Password storage | `password_hash()` / `password_verify()` with `PASSWORD_DEFAULT` (bcrypt). Correct. |
| Session fixation | `session_regenerate_id(true)` on both sign-in and sign-out. Correct. |
| Session cookies | `httponly`, `samesite=Lax`, `secure` when HTTPS. Correct. |
| CSRF | 23 mutating routes guarded in the router, plus 8 controllers calling `verify_csrf()` themselves. Token is 24 random bytes compared with `hash_equals()`. **One gap — see 5.1.** |
| File uploads | Type verified from the image data with `getimagesize()`, not the browser's filename; 6 MB cap; random filename; `move_uploaded_file()`; `chmod 0644`. Correct. |
| Path traversal | `product_image()` rejects any path containing `..`. Correct. |
| CSV injection | `export_csv()` prefixes cells starting with `= + @ - tab CR LF` with an apostrophe. This is a subtle one and it is handled. |
| Directory listing | `Options -Indexes`, and `.sql/.zip/.log/.ini/.cmd/.py/.md` denied. `config/` has its own `.htaccess`. |
| Self-lockout | The last active administrator cannot be demoted, deactivated or deleted. |

---

## 5. Findings

### 5.1 `notifications.open` changes state over GET without CSRF — low

`NotificationController::open()` calls `Notification::markRead()` and is not in
the router's CSRF list. A cross-site GET could mark a notification read.

No data is lost, no privilege changes, and the row is scoped to the signed-in
user — so the impact is cosmetic. But it is a state-changing GET, which is
worth knowing the name of. Either accept it deliberately (and say so in a
comment) or make the bell dropdown POST.

### 5.2 Live credentials sit in the working tree — **fix before pushing to Git**

- `config/photo-ai.local.php` holds a real Gemini API key.
- `config/mail.php` holds a real Gmail address and a 16-character app password.

Both are protected from the web (`config/.htaccess`, and `.php` is executed
rather than served), so this is **not** an exposure today. It becomes one the
moment the project is pushed to a public repository.

**Before any `git init`:** add these two paths to `.gitignore`, commit the
`.example` variants instead, and rotate both secrets — they have been in
plaintext on disk and in chat transcripts for weeks.

The project is currently **not** under version control (`.git` is absent), so
nothing has been pushed yet. There is still time to do this cleanly.

### 5.3 `Product::nextSku()` can collide — low

```php
$n = Database::value('SELECT COALESCE(MAX(id),0) + 1 FROM products');
return sprintf('AA-%s-%04d', strtoupper($code), $n);
```

Two people opening the "add item" form at once are suggested the same SKU. In
practice `createInternal()` saves with a temporary SKU and then rewrites it
from the real auto-increment id, so the *saved* value is always unique — and
`sku` is `UNIQUE` in the schema, so a genuine duplicate is impossible. The
collision is only ever in the suggestion shown on screen. Worth a comment so a
future reader does not "fix" the safe path by mistake.

### 5.4 `payment_method` enum vs. validation — cosmetic

The schema allows `'Cash','Installment','Bank Transfer'`, but
`PosController::checkout()` only accepts `Cash` and `Bank Transfer`.
`Installment` is unreachable. Either implement it or drop it from the enum, so
the schema stops promising something the code will not do.

### 5.5 `Auth::canEditItems()` returns true for everyone signed in

This is deliberate and the comment explains why — a container is unpacked by
whoever is on the floor, and making them wait for a manager just means items
sit unlisted. The money side is guarded separately by `canSetPrice()` and
`guardPriceChange()`. Flagged here only because a marker will ask about it, and
the answer is in the comment above the method.

### 5.6 No automated tests

There is no test suite. For a project with this much transactional logic —
discount allocation, invoice sequencing, idempotency, the price guard — that is
the one real gap. `Sale::allocate()` in particular is pure arithmetic with no
database dependency and could be unit-tested in twenty lines.

---

## 6. Requirements coverage

Every functional requirement referenced in the code is implemented, and the
`FR-xx` tags in the docblocks match what the code does:

| | Requirement | Where |
|---|---|---|
| FR-01 | Authentication, four roles, RBAC | `Auth`, router, `UserController` |
| FR-02 | Item cataloguing, categories | `Product`, `Category`, `InventoryController` |
| FR-03 | Barcodes per physical unit, QR labels, scanning | `ProductUnit`, `inventory/qr`, `pos.scan` |
| FR-04 | Point of sale | `PosController`, `Sale::checkout` |
| FR-04b | Discount request and approval | `DiscountRequest`, `ApprovalController` |
| FR-05 | Stock deduction on sale, movement ledger | `Product::deduct`, `stock_movements` |
| FR-06 | Reorder alerts, price approval | `Product::alerts`, `PriceRequest` |
| FR-07 | Daily/weekly/monthly/annual reports | `ReportController`, `Sale::trend` |
| FR-08 | Audit trail | `Audit`, `AuditController` |
| FR-09 | Decision support — forecasting | `Forecast` (least-squares + SMA) |
| FR-10 | Category sales for container planning | `ReportController::category` |
| FR-11 | Notifications | `Notification` |
| FR-11b | Email digest over SMTP | `Mailer` |

**FR-09 and FR-10 are the ones worth talking about in a defence.** The forecast
is a real least-squares linear regression computed on the shop's own data
(`Forecast::linearFit`), deliberately excluding the current incomplete month
from the fit. FR-10 exists because of a genuine business constraint: you cannot
order a named product from a surplus container, only a category — so the report
turns sales into a per-category "ask for more / keep receiving / ask for less"
recommendation with the reasoning spelled out.

---

## 7. What a marker will notice

**In your favour:** the comments. They explain *why*, not what — the race
conditions, the rounding, why the ZIP is hand-built, why historical rows say
"not captured" instead of guessing. The commit-free honesty of
`category_at_sale` being NULL for old rows is the kind of thing that reads as
genuine engineering judgement.

**Prepare answers for:**

1. Why track individual units instead of a quantity? (Second-hand goods are not
   interchangeable.)
2. Walk through `Sale::checkout()` and name what each lock protects against.
3. Why is the discount a percentage and not a peso amount? (It survives the
   cart changing.)
4. What is the cart fingerprint for? (Binding an approval to one exact sale.)
5. Why does `Xlsx.php` exist at all? (No `zip` extension, no Composer.)
6. Explain the least-squares fit in `Forecast::linearFit()`.

If you cannot explain a file, read it before the defence — that is the real
purpose of this document.

---

## 8. Recommended order of work

1. **Rotate the Gemini key and the Gmail app password, and write `.gitignore`
   before `git init`.** Everything else can wait; this cannot.
2. Add the `.gitignore`, then initialise the repository and commit in reviewed
   pieces.
3. Make `notifications.open` a POST, or comment the decision not to.
4. Unit-test `Sale::allocate()` and `Forecast::linearFit()` — both are pure
   functions, both are defensible in a viva, both take minutes.
5. Decide `Installment`: implement or remove.
6. Fix the MySQL service problem (see the separate note) so the system stops
   appearing to crash during demonstrations.
