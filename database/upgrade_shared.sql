/* =====================================================================
   SHARED DATABASE UPGRADE
   ---------------------------------------------------------------------
   Lets the C# desktop system (AASurplusTPS) live in the same database
   as the Smart Stock website, so a sale rung up on the till shows on
   the web dashboard and vice versa.

   Everything here is ADDITIVE. Existing columns are untouched and every
   new column has a default, so the website keeps working exactly as it
   did. Safe to run more than once.
   ===================================================================== */

/* ---- Customers -------------------------------------------------------
   The website stores a walk-in name as free text on the sale. The
   desktop system keeps a customer record. Both can now coexist:
   customer_name stays as it was, customer_id is used when the till
   picks a known customer.
   --------------------------------------------------------------------- */
CREATE TABLE IF NOT EXISTS `customers` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(120)  NOT NULL,
  `contact_no` VARCHAR(30)   NULL,
  `address`    VARCHAR(200)  NULL,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_customers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---- Sales: customer link, void status, remarks ---------------------
   The desktop system can void a sale and put the stock back. The
   website has no such concept, so the column defaults to 'Completed'
   and every existing row keeps its current meaning.
   --------------------------------------------------------------------- */
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'customer_id') = 0,
  'ALTER TABLE `sales` ADD COLUMN `customer_id` INT NULL AFTER `customer_name`',
  'DO 0'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'status') = 0,
  'ALTER TABLE `sales` ADD COLUMN `status` ENUM(''Completed'',''Voided'') NOT NULL DEFAULT ''Completed'' AFTER `payment_method`',
  'DO 0'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = 'remarks') = 0,
  'ALTER TABLE `sales` ADD COLUMN `remarks` VARCHAR(200) NULL AFTER `status`',
  'DO 0'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* ---- Categories: active flag --------------------------------------- */
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND COLUMN_NAME = 'status') = 0,
  'ALTER TABLE `categories` ADD COLUMN `status` ENUM(''active'',''inactive'') NOT NULL DEFAULT ''active''',
  'DO 0'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* ---- Audit log: which record, and which machine --------------------
   The website records the IP address. The desktop system records the
   workstation name and the id of the row that changed. Both are kept.
   --------------------------------------------------------------------- */
SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'record_id') = 0,
  'ALTER TABLE `audit_logs` ADD COLUMN `record_id` VARCHAR(30) NULL AFTER `action`',
  'DO 0'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs' AND COLUMN_NAME = 'machine_name') = 0,
  'ALTER TABLE `audit_logs` ADD COLUMN `machine_name` VARCHAR(60) NULL AFTER `ip_address`',
  'DO 0'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* ---- Audit detail length ---------------------------------------------
   The website writes short descriptions; the desktop system writes a
   fuller sentence (up to 400 characters), including before/after values
   on an update. Widen the column so neither has to truncate.
   --------------------------------------------------------------------- */
SET @sql := (SELECT IF(
  (SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'
     AND COLUMN_NAME = 'description') < 400,
  'ALTER TABLE `audit_logs` MODIFY COLUMN `description` VARCHAR(400) NULL',
  'DO 0'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* ---- Compatibility view ---------------------------------------------
   The desktop system's reports read a view called vwProductStock, which
   exists in its SQL Server database. Recreating it here, with the same
   column names, means those reports work unchanged against the shared
   database. The website does not use it, so nothing there is affected.
   --------------------------------------------------------------------- */
DROP VIEW IF EXISTS `vwProductStock`;
CREATE VIEW `vwProductStock` AS
SELECT  p.id                AS ProductId,
        p.sku               AS Sku,
        p.name              AS ProductName,
        c.name              AS CategoryName,
        p.brand             AS Brand,
        p.condition_grade   AS ConditionGrade,
        p.cost_price        AS CostPrice,
        p.selling_price     AS SellingPrice,
        p.quantity          AS QuantityOnHand,
        p.reorder_level     AS ReorderLevel,
        CAST(p.quantity * p.cost_price AS DECIMAL(14,2)) AS StockValue,
        CASE WHEN p.quantity = 0                THEN 'Out of Stock'
             WHEN p.quantity <= p.reorder_level THEN 'Low Stock'
             ELSE 'In Stock'
        END                 AS StockStatus,
        (p.status = 'active') AS IsActive
FROM    products AS p
JOIN    categories AS c ON c.id = p.category_id;
