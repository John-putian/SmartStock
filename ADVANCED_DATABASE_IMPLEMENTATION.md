# Advanced Database Systems: Database Normalization in Smart Stock

**System:** Smart Stock, the Web-Based Inventory, Sales and Decision Support System for A&A Surplus (IT 307)
**Database:** MariaDB 10.4.32 on XAMPP, database `aa_inventory`
**Topic:** Database normalization: UNF → 1NF → 2NF → 3NF, primary and foreign keys, entity relationships, referential integrity

---

## 1. Topic and where it is applied

| Item | Detail |
|---|---|
| Topic | Database normalization (UNF, 1NF, 2NF, 3NF) |
| Module | **Point of Sale / Sales**: the receipts a cashier rings up at the till |
| Real tables involved | `sales`, `sale_items`, `products`, `categories`, `users` |
| Sample used | The 10 most recent real sales in `aa_inventory` that have more than one item (21 sale lines) |

## 2. Purpose

A paper sales register writes each receipt as one row, with every item crammed into the same row. That causes:

- **Repeating groups.** One receipt holds many items, so the item columns hold many values.
- **Redundancy.** The cashier's name and every product's name and category are written again on every receipt.
- **Update, insert and delete anomalies.** Renaming a product means changing many rows. A product can't be recorded until somebody buys it. Deleting the only receipt for a product erases the product too.

Normalization removes these problems step by step. The final 3NF design is the one Smart Stock actually uses.

## 3. Implementation

Two scripts are in `database/ads/`:

| File | What it does |
|---|---|
| `normalization_demo.sql` | Builds a separate database, `aa_normalization_demo`, from real `aa_inventory` rows: UNF table → 1NF table → 2NF tables → 3NF tables. It only **reads** `aa_inventory`. |
| `normalization_proof.sql` | Seven proof queries, each run on its own during the demonstration. |
| `normalization_proof_results.txt` | The actual output of the proof queries from the laptop run. |

### UNF: Unnormalized Form (`unf_sales_register`)

One row per receipt. The items are packed into multi-valued columns separated by `|`, and there is no primary key.

| invoice_no | cashier_name | item_skus | item_names | item_unit_prices |
|---|---|---|---|---|
| INV-202601095 | John Ray Puti-an | AA-FUR-0001 \| AA-FUR-0006 \| AA-FUR-0021 | Fabric Tub Accent Chair \| Wooden Chest of Drawers \| Cream Console Table 2-Drawer | 2800.00 \| 4200.00 \| 3400.00 |

**Violates 1NF:** the columns are not atomic, and the items are a repeating group.

### 1NF: First Normal Form (`nf1_sales_lines`)

**Rule:** every column holds one atomic value, there are no repeating groups, and every row has a primary key.
**Fix:** each receipt is split into one row per item, directly from the UNF table. The primary key is the composite **(invoice_no, sku)**.

| invoice_no | sku | product_name | category_name | quantity | unit_price | cashier_name |
|---|---|---|---|---|---|---|
| INV-202601095 | AA-FUR-0001 | Fabric Tub Accent Chair | Furniture | 1 | 2800.00 | John Ray Puti-an |
| INV-202601095 | AA-FUR-0006 | Wooden Chest of Drawers | Furniture | 1 | 4200.00 | John Ray Puti-an |
| INV-202601095 | AA-FUR-0021 | Cream Console Table 2-Drawer | Furniture | 1 | 3400.00 | John Ray Puti-an |

**Still a problem:** partial dependencies, so data repeats. In the 21 lines, *Rossen Dale Dizon* is stored 12 times and *John Ray Puti-an* 9 times. Five products are each stored twice.

### 2NF: Second Normal Form

**Rule:** in 1NF, and every non-key column depends on the **whole** composite key, not just part of it.

Partial dependencies found:

- `invoice_no → sale_date, cashier, customer_name, payment_method, total`. These depend on the receipt only.
- `sku → product_name, category_name, category_code`. These depend on the product only.
- Only `quantity` and `unit_price` depend on **(invoice_no, sku)** together. `unit_price` stays on the line on purpose: it is the price *this* sale was charged, and listed prices change through Price Approvals.

**Fix:** split the 1NF table into three:

| Table | Primary key | Holds |
|---|---|---|
| `nf2_sales` | invoice_no | receipt header |
| `nf2_products` | sku | product facts |
| `nf2_sale_items` | (invoice_no, sku), both foreign keys | quantity, unit_price |

### 3NF: Third Normal Form

**Rule:** in 2NF, and no **transitive** dependency, meaning no non-key column depends on another non-key column.

Transitive dependencies found:

- `nf2_sales`: `invoice_no → cashier_username → cashier_name, cashier_role`
- `nf2_products`: `sku → category_name → category_code`

**Fix:** move them into their own tables and keep only a foreign key. Integer surrogate ids are used, as in the live system.

```
users (id PK, username UNIQUE, full_name, role)
categories (id PK, code, name UNIQUE)
products (id PK, sku UNIQUE, name, category_id FK → categories.id)
sales (id PK, invoice_no UNIQUE, sale_date, user_id FK → users.id, customer_name, payment_method, total)
sale_items (id PK, sale_id FK → sales.id, product_id FK → products.id, quantity, unit_price)
```

### Entity relationships

- **categories 1 — N products.** A category has many products; each product has one category.
- **users 1 — N sales.** A cashier rings up many sales; each sale has one cashier.
- **sales 1 — N sale_items.** A receipt has many lines. Deleting a sale cascades to its lines.
- **products 1 — N sale_items.** A product appears on many receipts.

This is the same structure as the live `aa_inventory` tables. Proof 7 reads the real foreign keys from `information_schema`.

### A note on the live `sale_items` table

The live `sale_items` also keeps `product_name`, `sku`, `category_at_sale` and `cost_price_at_sale`. That is **deliberate denormalization**. A receipt is a financial record, so it must show what was sold, at what name and category, on that day, even if the product is renamed or moved later. The 3NF demo leaves these columns out to show the pure design, and the live system keeps them as a historical snapshot.

## 4. Queries and expected vs. actual results

Run on 20 Nov 2026 against the real database. The full output is in `normalization_proof_results.txt`.

| # | Proof | Expected | Actual |
|---|---|---|---|
| 1 | UNF has repeating groups | Several items in one row | 10 receipts with 2 to 3 items each in one row ✅ |
| 2 | 1NF is atomic | One row per item | 21 rows, key (invoice_no, sku) ✅ |
| 3 | Redundancy in 1NF, gone in 3NF | Repeated names in 1NF, each stored once in 3NF | Cashier stored 12 and 9 times, 5 products twice. In 3NF: 16 products, 2 users, 5 categories, each stored once ✅ |
| 4 | Row counts per stage | UNF < 1NF; 1NF lines = 2NF lines = 3NF lines | UNF 10 · 1NF 21 · 2NF 10 / 16 / 21 · 3NF 2 / 5 / 16 / 10 / 21 ✅ |
| 5 | Lossless decomposition | Joining 3NF back gives 1NF exactly: 0 missing, 0 invented | **0 and 0** ✅ |
| 6 | Referential integrity | Insert a line for a product that doesn't exist and get it refused | `ERROR 1452 … foreign key constraint fails (fk_items_product)` ✅ |
| 7 | 3NF matches the live system | Same foreign keys in `aa_inventory` | products→categories, sales→users, sale_items→products, sale_items→sales ✅ |

Safety check: `CHECKSUM TABLE` on `aa_inventory.sales`, `sale_items`, `products`, `users` and `categories` gave the same values before and after the script ran. **The live data was not changed.**

> The sample is "the 10 most recent multi-item sales", so if new sales are rung up before the recording, different receipts will appear. The proofs still hold; only the names and numbers change.

## 5. How to demonstrate it

1. Start XAMPP (Apache and MySQL), or double-click `START-DEMO.cmd`.
2. Open **http://localhost/phpmyadmin**.
3. Click the **SQL** tab. Open `database/ads/normalization_demo.sql` in Notepad or VS Code, copy all of it, paste it and click **Go**. (Or run `C:\xampp\mysql\bin\mysql.exe -uroot < normalization_demo.sql` from that folder.)
4. Click **aa_normalization_demo** in the left panel. It has 10 tables: `unf_…`, `nf1_…`, `nf2_…` and the five 3NF tables.
5. Open the **SQL** tab of `aa_normalization_demo` and run the blocks of `normalization_proof.sql` **one at a time** (Proof 1, 2, 3 …), so each result grid shows on screen.
6. For the relationships, open `aa_normalization_demo` → **Designer** (under *More*) to show the ER diagram.
7. Finish in Smart Stock itself (http://localhost/SmartStock/, cashier sign-in) to show the till where these sales come from.

## 6. Evidence

- `database/ads/normalization_proof_results.txt`: the actual output from the laptop.
- The screen recording (see the plan below).
- Screenshots to take for the submission: the UNF grid, the 1NF grid, the 2NF and 3NF table lists, the Proof 5 result (0 / 0), the Proof 6 error, and the Designer diagram.

---

# Video and voice-over

The demonstration video is `database/ads/SmartStock_Normalization_captions.mp4` (3:53, 1080p, captions) with a caption-free copy, `SmartStock_Normalization_clean.mp4`. It is a recording of the **Normalization Explorer** page added to Smart Stock (`normalization-explorer.php`, read-only, localhost only), which reads the real `aa_normalization_demo` tables.

The narration, line by line with start times, is in `database/ads/VOICE-OVER-SCRIPT.md`. Record your own voice over the video in Clipchamp or CapCut.

To show it live instead: open **http://localhost/SmartStock/normalization-explorer.php** and click through the sidebar from top to bottom.

---

# Google Classroom submission checklist

- [ ] Video with your voice-over uploaded (3:53)
- [ ] `ADVANCED_DATABASE_IMPLEMENTATION.md` (this file), or a PDF of it
- [ ] `normalization_demo.sql` and `normalization_proof.sql`
- [ ] `normalization_proof_results.txt`
- [ ] Screenshots: UNF, 1NF, 2NF/3NF tables, Proof 5, Proof 6 error, Designer diagram
- [ ] GitHub link, if required. **Before pushing**, make sure `config/mail.php` and `config/photo-ai.local.php` (Gmail app password and Gemini key) are in `.gitignore` and not committed.
- [ ] Group member names on the submission
