-- =====================================================================
--  Smart Stock (A&A Surplus) - Database Normalization Demonstration
--  Advanced Database Systems
--
--  Walks a real sales record from Unnormalized Form (UNF) to Third
--  Normal Form (3NF), using actual rows from the live `aa_inventory`
--  database.
--
--  SAFE TO RUN: it only READS from `aa_inventory`. Everything it builds
--  goes into a separate database, `aa_normalization_demo`, which it
--  drops and recreates each time.
--
--  Run in phpMyAdmin (SQL tab, paste, Go) or from a command prompt:
--      C:\xampp\mysql\bin\mysql.exe -uroot < normalization_demo.sql
-- =====================================================================

DROP DATABASE IF EXISTS `aa_normalization_demo`;
CREATE DATABASE `aa_normalization_demo`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `aa_normalization_demo`;

-- ---------------------------------------------------------------------
--  Sample: the 10 most recent real sales that have more than one item.
-- ---------------------------------------------------------------------
CREATE TEMPORARY TABLE `sample_sales` AS
SELECT s.id
FROM aa_inventory.sales s
WHERE s.user_id IS NOT NULL
  AND (SELECT COUNT(*) FROM aa_inventory.sale_items si WHERE si.sale_id = s.id) > 1
ORDER BY s.sale_date DESC, s.id DESC
LIMIT 10;


-- =====================================================================
--  UNF - UNNORMALIZED FORM
--  One row per receipt, the way a paper sales register is written.
--  The items are REPEATING GROUPS packed into multi-valued columns
--  (separated by " | "), and cashier and product details are repeated
--  on every receipt. There is no primary key.
-- =====================================================================
CREATE TABLE `unf_sales_register` (
  `invoice_no`        VARCHAR(30),
  `sale_date`         DATETIME,
  `cashier_username`  VARCHAR(60),
  `cashier_name`      VARCHAR(120),
  `cashier_role`      VARCHAR(20),
  `customer_name`     VARCHAR(120),
  `payment_method`    VARCHAR(20),
  `item_skus`         TEXT,
  `item_names`        TEXT,
  `item_categories`   TEXT,
  `category_codes`    TEXT,
  `item_quantities`   TEXT,
  `item_unit_prices`  TEXT,
  `total`             DECIMAL(12,2)
) ENGINE=InnoDB;

INSERT INTO `unf_sales_register`
SELECT
  s.invoice_no,
  s.sale_date,
  u.username,
  u.full_name,
  u.role,
  s.customer_name,
  s.payment_method,
  GROUP_CONCAT(si.sku           ORDER BY si.id SEPARATOR ' | '),
  GROUP_CONCAT(si.product_name  ORDER BY si.id SEPARATOR ' | '),
  GROUP_CONCAT(c.name           ORDER BY si.id SEPARATOR ' | '),
  GROUP_CONCAT(c.code           ORDER BY si.id SEPARATOR ' | '),
  GROUP_CONCAT(si.quantity      ORDER BY si.id SEPARATOR ' | '),
  GROUP_CONCAT(si.unit_price    ORDER BY si.id SEPARATOR ' | '),
  s.total
FROM aa_inventory.sales s
JOIN sample_sales     x  ON x.id = s.id
JOIN aa_inventory.users      u  ON u.id  = s.user_id
JOIN aa_inventory.sale_items si ON si.sale_id = s.id
JOIN aa_inventory.products   p  ON p.id  = si.product_id
JOIN aa_inventory.categories c  ON c.id  = p.category_id
GROUP BY s.id;


-- =====================================================================
--  1NF - FIRST NORMAL FORM
--  Rule: every column holds ONE atomic value, no repeating groups,
--  and each row is identified by a primary key.
--  Fix:  split each receipt into one row per item. The key becomes the
--        composite (invoice_no, sku).
--  The split is done FROM the UNF table, position by position.
-- =====================================================================
CREATE TABLE `nf1_sales_lines` (
  `invoice_no`        VARCHAR(30)   NOT NULL,
  `sku`               VARCHAR(40)   NOT NULL,
  `sale_date`         DATETIME      NOT NULL,
  `cashier_username`  VARCHAR(60)   NOT NULL,
  `cashier_name`      VARCHAR(120)  NOT NULL,
  `cashier_role`      VARCHAR(20)   NOT NULL,
  `customer_name`     VARCHAR(120)  NULL,
  `payment_method`    VARCHAR(20)   NOT NULL,
  `product_name`      VARCHAR(150)  NOT NULL,
  `category_name`     VARCHAR(80)   NOT NULL,
  `category_code`     CHAR(3)       NOT NULL,
  `quantity`          INT           NOT NULL,
  `unit_price`        DECIMAL(12,2) NOT NULL,
  `total`             DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`invoice_no`, `sku`)
) ENGINE=InnoDB;

INSERT INTO `nf1_sales_lines`
SELECT
  r.invoice_no,
  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(r.item_skus,        ' | ', n.pos), ' | ', -1)),
  r.sale_date, r.cashier_username, r.cashier_name, r.cashier_role,
  r.customer_name, r.payment_method,
  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(r.item_names,       ' | ', n.pos), ' | ', -1)),
  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(r.item_categories,  ' | ', n.pos), ' | ', -1)),
  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(r.category_codes,   ' | ', n.pos), ' | ', -1)),
  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(r.item_quantities,  ' | ', n.pos), ' | ', -1)),
  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(r.item_unit_prices, ' | ', n.pos), ' | ', -1)),
  r.total
FROM `unf_sales_register` r
JOIN (SELECT 1 AS pos UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
      UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8) n
  ON n.pos <= 1 + (LENGTH(r.item_skus) - LENGTH(REPLACE(r.item_skus, ' | ', ''))) / 3;


-- =====================================================================
--  2NF - SECOND NORMAL FORM
--  Rule: in 1NF, and no PARTIAL dependency - every non-key column must
--  depend on the WHOLE composite key (invoice_no, sku), not part of it.
--
--  Partial dependencies found in nf1_sales_lines:
--    invoice_no -> sale_date, cashier_*, customer_name, payment_method, total
--    sku        -> product_name, category_name, category_code
--  Only quantity and unit_price depend on (invoice_no, sku) together.
--  (unit_price stays on the line on purpose: it is the price THIS sale
--   was charged, and listed prices change through Price Approvals.)
-- =====================================================================
CREATE TABLE `nf2_sales` (
  `invoice_no`        VARCHAR(30)   NOT NULL PRIMARY KEY,
  `sale_date`         DATETIME      NOT NULL,
  `cashier_username`  VARCHAR(60)   NOT NULL,
  `cashier_name`      VARCHAR(120)  NOT NULL,
  `cashier_role`      VARCHAR(20)   NOT NULL,
  `customer_name`     VARCHAR(120)  NULL,
  `payment_method`    VARCHAR(20)   NOT NULL,
  `total`             DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE `nf2_products` (
  `sku`               VARCHAR(40)   NOT NULL PRIMARY KEY,
  `product_name`      VARCHAR(150)  NOT NULL,
  `category_name`     VARCHAR(80)   NOT NULL,
  `category_code`     CHAR(3)       NOT NULL
) ENGINE=InnoDB;

CREATE TABLE `nf2_sale_items` (
  `invoice_no`        VARCHAR(30)   NOT NULL,
  `sku`               VARCHAR(40)   NOT NULL,
  `quantity`          INT           NOT NULL,
  `unit_price`        DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`invoice_no`, `sku`),
  CONSTRAINT `fk_nf2_items_sale`    FOREIGN KEY (`invoice_no`) REFERENCES `nf2_sales`(`invoice_no`),
  CONSTRAINT `fk_nf2_items_product` FOREIGN KEY (`sku`)        REFERENCES `nf2_products`(`sku`)
) ENGINE=InnoDB;

INSERT INTO `nf2_sales`
SELECT DISTINCT invoice_no, sale_date, cashier_username, cashier_name,
                cashier_role, customer_name, payment_method, total
FROM `nf1_sales_lines`;

INSERT INTO `nf2_products`
SELECT DISTINCT sku, product_name, category_name, category_code
FROM `nf1_sales_lines`;

INSERT INTO `nf2_sale_items`
SELECT invoice_no, sku, quantity, unit_price
FROM `nf1_sales_lines`;


-- =====================================================================
--  3NF - THIRD NORMAL FORM
--  Rule: in 2NF, and no TRANSITIVE dependency - a non-key column must
--  not depend on another non-key column.
--
--  Transitive dependencies found in 2NF:
--    nf2_sales:    invoice_no -> cashier_username -> cashier_name, cashier_role
--    nf2_products: sku -> category_name -> category_code
--  Fix: move them to their own tables (users, categories) and keep only
--  a foreign key. Surrogate integer ids are introduced, exactly as the
--  live Smart Stock tables use them.
--  The result has the same shape as aa_inventory's real
--  users / categories / products / sales / sale_items tables.
-- =====================================================================
CREATE TABLE `users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(60)  NOT NULL UNIQUE,
  `full_name`  VARCHAR(120) NOT NULL,
  `role`       VARCHAR(20)  NOT NULL
) ENGINE=InnoDB;

CREATE TABLE `categories` (
  `id`    INT AUTO_INCREMENT PRIMARY KEY,
  `code`  CHAR(3)     NOT NULL,
  `name`  VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE `products` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `sku`          VARCHAR(40)  NOT NULL UNIQUE,
  `name`         VARCHAR(150) NOT NULL,
  `category_id`  INT          NOT NULL,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `sales` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no`      VARCHAR(30)   NOT NULL UNIQUE,
  `sale_date`       DATETIME      NOT NULL,
  `user_id`         INT           NOT NULL,
  `customer_name`   VARCHAR(120)  NULL,
  `payment_method`  VARCHAR(20)   NOT NULL,
  `total`           DECIMAL(12,2) NOT NULL,
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `sale_items` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id`     INT           NOT NULL,
  `product_id`  INT           NOT NULL,
  `quantity`    INT           NOT NULL,
  `unit_price`  DECIMAL(12,2) NOT NULL,
  UNIQUE KEY `uq_sale_product` (`sale_id`, `product_id`),
  CONSTRAINT `fk_items_sale`    FOREIGN KEY (`sale_id`)    REFERENCES `sales`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

INSERT INTO `users` (`username`, `full_name`, `role`)
SELECT DISTINCT cashier_username, cashier_name, cashier_role
FROM `nf2_sales` ORDER BY cashier_username;

INSERT INTO `categories` (`code`, `name`)
SELECT DISTINCT category_code, category_name
FROM `nf2_products` ORDER BY category_name;

INSERT INTO `products` (`sku`, `name`, `category_id`)
SELECT p.sku, p.product_name, c.id
FROM `nf2_products` p
JOIN `categories` c ON c.name = p.category_name
ORDER BY p.sku;

INSERT INTO `sales` (`invoice_no`, `sale_date`, `user_id`, `customer_name`, `payment_method`, `total`)
SELECT s.invoice_no, s.sale_date, u.id, s.customer_name, s.payment_method, s.total
FROM `nf2_sales` s
JOIN `users` u ON u.username = s.cashier_username
ORDER BY s.sale_date;

INSERT INTO `sale_items` (`sale_id`, `product_id`, `quantity`, `unit_price`)
SELECT s.id, p.id, i.quantity, i.unit_price
FROM `nf2_sale_items` i
JOIN `sales`    s ON s.invoice_no = i.invoice_no
JOIN `products` p ON p.sku        = i.sku
ORDER BY s.id, p.id;

DROP TEMPORARY TABLE `sample_sales`;

-- Built. Run normalization_proof.sql next to verify every stage.
SELECT 'aa_normalization_demo built: UNF -> 1NF -> 2NF -> 3NF' AS result;
