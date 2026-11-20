-- =====================================================================
--  Smart Stock - Normalization PROOF queries
--  Run AFTER normalization_demo.sql. Each block is meant to be run on
--  its own during the recording (phpMyAdmin > aa_normalization_demo >
--  SQL tab), so the result grid appears on screen.
--  Read-only, except Proof 6, which runs inside a transaction and is
--  rolled back.
-- =====================================================================
USE `aa_normalization_demo`;

-- ---------------------------------------------------------------------
-- Proof 1 - UNF: repeating groups. One receipt row holds many items.
-- ---------------------------------------------------------------------
SELECT invoice_no, cashier_name, item_skus, item_names, item_quantities, item_unit_prices
FROM unf_sales_register
ORDER BY invoice_no DESC;

-- ---------------------------------------------------------------------
-- Proof 2 - 1NF: one atomic value per column, one row per item.
-- Same receipts, now one line per item with a composite key.
-- ---------------------------------------------------------------------
SELECT invoice_no, sku, product_name, category_name, quantity, unit_price, cashier_name
FROM nf1_sales_lines
ORDER BY invoice_no DESC, sku;

-- ---------------------------------------------------------------------
-- Proof 3 - Why 1NF is not enough: redundancy (update anomaly).
-- The same product and the same cashier are stored again on every line.
-- Renaming one would mean updating many rows.
-- ---------------------------------------------------------------------
SELECT 'product name' AS repeated_fact, product_name AS value, COUNT(*) AS times_stored
FROM nf1_sales_lines GROUP BY product_name HAVING COUNT(*) > 1
UNION ALL
SELECT 'cashier name', cashier_name, COUNT(*)
FROM nf1_sales_lines GROUP BY cashier_name HAVING COUNT(*) > 1
ORDER BY times_stored DESC;

-- After 3NF each of those facts is stored exactly once:
SELECT 'products' AS tbl, COUNT(*) AS rows_, COUNT(DISTINCT name)     AS distinct_values FROM products
UNION ALL SELECT 'users',      COUNT(*), COUNT(DISTINCT username) FROM users
UNION ALL SELECT 'categories', COUNT(*), COUNT(DISTINCT name)     FROM categories;

-- ---------------------------------------------------------------------
-- Proof 4 - Row counts at every stage.
-- ---------------------------------------------------------------------
SELECT 'UNF  unf_sales_register' AS stage_table, COUNT(*) AS row_count FROM unf_sales_register
UNION ALL SELECT '1NF  nf1_sales_lines',  COUNT(*) FROM nf1_sales_lines
UNION ALL SELECT '2NF  nf2_sales',        COUNT(*) FROM nf2_sales
UNION ALL SELECT '2NF  nf2_products',     COUNT(*) FROM nf2_products
UNION ALL SELECT '2NF  nf2_sale_items',   COUNT(*) FROM nf2_sale_items
UNION ALL SELECT '3NF  users',            COUNT(*) FROM users
UNION ALL SELECT '3NF  categories',       COUNT(*) FROM categories
UNION ALL SELECT '3NF  products',         COUNT(*) FROM products
UNION ALL SELECT '3NF  sales',            COUNT(*) FROM sales
UNION ALL SELECT '3NF  sale_items',       COUNT(*) FROM sale_items;

-- ---------------------------------------------------------------------
-- Proof 5 - Lossless decomposition. Joining the 3NF tables back
-- together rebuilds the 1NF table exactly: no row lost, none invented.
-- Both counts must be 0.
-- ---------------------------------------------------------------------
WITH rebuilt AS (
  SELECT s.invoice_no, p.sku, s.sale_date, u.username AS cashier_username,
         u.full_name AS cashier_name, u.role AS cashier_role, s.customer_name,
         s.payment_method, p.name AS product_name, c.name AS category_name,
         c.code AS category_code, i.quantity, i.unit_price, s.total
  FROM sale_items i
  JOIN sales      s ON s.id = i.sale_id
  JOIN users      u ON u.id = s.user_id
  JOIN products   p ON p.id = i.product_id
  JOIN categories c ON c.id = p.category_id
)
SELECT
  (SELECT COUNT(*) FROM (SELECT * FROM nf1_sales_lines EXCEPT SELECT * FROM rebuilt) a) AS rows_missing_after_join,
  (SELECT COUNT(*) FROM (SELECT * FROM rebuilt EXCEPT SELECT * FROM nf1_sales_lines) b) AS rows_invented_by_join;

-- ---------------------------------------------------------------------
-- Proof 6 - Referential integrity. A sale line that points at a product
-- that does not exist is refused by the foreign key (error 1452).
-- Run the three lines together. The ROLLBACK leaves nothing behind.
-- ---------------------------------------------------------------------
START TRANSACTION;
INSERT INTO sale_items (sale_id, product_id, quantity, unit_price) VALUES (1, 99999, 1, 100.00);
ROLLBACK;

-- And a product cannot be deleted while sales still point at it (also 1451):
-- DELETE FROM products WHERE id = 1;

-- ---------------------------------------------------------------------
-- Proof 7 - The 3NF result matches the LIVE Smart Stock design.
-- Same tables and the same foreign keys in the real aa_inventory database.
-- ---------------------------------------------------------------------
SELECT TABLE_NAME AS child_table, COLUMN_NAME AS foreign_key,
       REFERENCED_TABLE_NAME AS parent_table, REFERENCED_COLUMN_NAME AS parent_key
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'aa_inventory'
  AND TABLE_NAME IN ('products', 'sales', 'sale_items')
  AND REFERENCED_TABLE_NAME IN ('categories', 'users', 'products', 'sales')
  AND COLUMN_NAME <> 'discount_approved_by'
ORDER BY child_table, foreign_key;
