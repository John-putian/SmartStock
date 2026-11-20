-- =====================================================================
--  WEB-BASED INVENTORY, SALES, AND DECISION SUPPORT SYSTEM
--  A&A Household Appliances Trading - Panabo City, Davao del Norte
--  IT 307 - Web Systems and Technologies 2
--  Proponents: Carl Ulrick Duque | Rossen Dale Dizon | John Ray Puti-an
--  ---------------------------------------------------------------
--  DATABASE SCHEMA (structure only - see seed.sql for sample data)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `aa_inventory`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `aa_inventory`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `discount_requests`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `price_requests`;
DROP TABLE IF EXISTS `sale_items`;
DROP TABLE IF EXISTS `sales`;
DROP TABLE IF EXISTS `stock_movements`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `product_units`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;
-- The customer watchlist was withdrawn; this clears it from older installs.
DROP TABLE IF EXISTS `watchlist_matches`;
DROP TABLE IF EXISTS `watchlists`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- FR-01 : Users and role-based access
--
--   admin    - Administrator. The whole system, including user accounts.
--   manager  - Store Manager. Everything except user accounts. Together with
--              the administrator these are the two "authorising" roles: only
--              they may lower a selling price or approve a discount.
--   staff    - Work Staff. The shop floor. Receives containers, registers new
--              items and sets their list price, adjusts counts, sells at the
--              till. May raise a price, but a REDUCTION becomes a request that
--              an administrator or manager has to approve.
--   cashier  - Cashier. Sells at the till and watches stock come in and go
--              out. Read-only on the catalogue, and no access to Insights.
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `full_name`     VARCHAR(120)  NOT NULL,
  `username`      VARCHAR(60)   NOT NULL UNIQUE,
  `password_hash` VARCHAR(255)  NOT NULL,
  `role`          ENUM('admin','manager','staff','cashier') NOT NULL DEFAULT 'cashier',
  `contact_no`    VARCHAR(30)   DEFAULT NULL,
  -- Where notices of the kinds listed in config/mail.php are also sent.
  -- Blank means this account gets the bell only.
  `email`         VARCHAR(160)  DEFAULT NULL,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_login`    DATETIME      DEFAULT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- FR-02 : Product categories
-- ---------------------------------------------------------------------
CREATE TABLE `categories` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `code`        CHAR(3)      NOT NULL DEFAULT 'GEN',
  `name`        VARCHAR(80)  NOT NULL UNIQUE,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- FR-02 / FR-03 / FR-06 : Inventory items
-- ---------------------------------------------------------------------
CREATE TABLE `products` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `sku`              VARCHAR(40)  NOT NULL UNIQUE,
  `serial_no`        VARCHAR(60)  DEFAULT NULL,
  `name`             VARCHAR(150) NOT NULL,
  `category_id`      INT          DEFAULT NULL,
  `brand`            VARCHAR(80)  DEFAULT NULL,
  `description`      TEXT         DEFAULT NULL,
  `condition_grade`  ENUM('Class A','Class B','Class C','For Parts') NOT NULL DEFAULT 'Class B',
  `cost_price`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `selling_price`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `quantity`         INT          NOT NULL DEFAULT 0,
  `image_path`       VARCHAR(180) DEFAULT NULL,
  `reorder_level`    INT          NOT NULL DEFAULT 3,
  `status`           ENUM('active','archived') NOT NULL DEFAULT 'active',
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
      REFERENCES `categories`(`id`) ON DELETE SET NULL,
  INDEX `idx_products_name` (`name`),
  INDEX `idx_products_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- FR-02 / FR-03 : Individual physical units
--
--   Second-hand stock is one-of-a-kind. One row here is ONE physical piece
--   on the shop floor, with its own barcode. A one-off table therefore has
--   a single unit; five matching chairs have five, each separately labelled,
--   condition-graded and sellable.
--
--   The barcode reads  AA-FUR-0007-03
--                      |  |   |    +-- unit number within the item
--                      |  |   +------- item number
--                      |  +----------- category code
--                      +-------------- store prefix
-- ---------------------------------------------------------------------
CREATE TABLE `product_units` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `product_id`      INT          NOT NULL,
  `unit_code`       VARCHAR(48)  NOT NULL UNIQUE,
  `unit_no`         INT          NOT NULL DEFAULT 1,
  `condition_grade` ENUM('Class A','Class B','Class C','For Parts') DEFAULT NULL,
  `notes`           VARCHAR(255) DEFAULT NULL,
  `status`          ENUM('in_stock','sold','reserved','retired') NOT NULL DEFAULT 'in_stock',
  `sale_id`         INT          DEFAULT NULL,
  `received_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sold_at`         DATETIME     DEFAULT NULL,
  CONSTRAINT `fk_unit_product` FOREIGN KEY (`product_id`)
      REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_unit_product` (`product_id`),
  INDEX `idx_unit_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- FR-04 : Point-of-sale transactions
-- ---------------------------------------------------------------------
CREATE TABLE `sales` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no`     VARCHAR(30)   NOT NULL UNIQUE,
  `user_id`        INT           DEFAULT NULL,
  `customer_name`  VARCHAR(120)  DEFAULT NULL,
  `subtotal`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `change_due`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('Cash','Installment','Bank Transfer') NOT NULL DEFAULT 'Cash',
  -- Who authorised the discount. A sale with a discount and no approver here
  -- cannot be recorded: the till asks for an administrator or manager sign-off.
  `discount_percent`     DECIMAL(5,2)  NOT NULL DEFAULT 0,
  `discount_approved_by` INT       DEFAULT NULL,
  `sale_date`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sales_user` FOREIGN KEY (`user_id`)
      REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_sales_approver` FOREIGN KEY (`discount_approved_by`)
      REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_sales_date` (`sale_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sale_items` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id`      INT           NOT NULL,
  `product_id`   INT           DEFAULT NULL,
  `product_name` VARCHAR(150)  NOT NULL,
  `sku`          VARCHAR(40)   DEFAULT NULL,
  `unit_price`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `quantity`     INT           NOT NULL DEFAULT 1,
  `line_total`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_items_sale` FOREIGN KEY (`sale_id`)
      REFERENCES `sales`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`)
      REFERENCES `products`(`id`) ON DELETE SET NULL,
  INDEX `idx_items_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- FR-05 / FR-07 : Stock movement ledger (in / out / adjustment)
-- ---------------------------------------------------------------------
CREATE TABLE `stock_movements` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `type`       ENUM('in','out','adjust') NOT NULL,
  `quantity`   INT NOT NULL,
  `balance`    INT NOT NULL DEFAULT 0,
  `reference`  VARCHAR(60)  DEFAULT NULL,
  `notes`      VARCHAR(255) DEFAULT NULL,
  `user_id`    INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_move_product` FOREIGN KEY (`product_id`)
      REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_move_user` FOREIGN KEY (`user_id`)
      REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_move_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- FR-01 / FR-06 : Price reduction requests
--
--   Work staff set the list price of an item they have just received, and may
--   raise it later. Lowering a price is where money quietly leaks away, so a
--   reduction asked for by anyone below manager level lands here as a pending
--   request. The item keeps its old price until an administrator or manager
--   approves it, and every decision is stamped with who made it and when.
-- ---------------------------------------------------------------------
CREATE TABLE `price_requests` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `product_id`    INT           NOT NULL,
  `requested_by`  INT           DEFAULT NULL,
  `old_price`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `new_price`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `reason`        VARCHAR(255)  DEFAULT NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `decided_by`    INT           DEFAULT NULL,
  `decision_note` VARCHAR(255)  DEFAULT NULL,
  `decided_at`    DATETIME      DEFAULT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pricereq_product` FOREIGN KEY (`product_id`)
      REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pricereq_asker` FOREIGN KEY (`requested_by`)
      REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pricereq_judge` FOREIGN KEY (`decided_by`)
      REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_pricereq_status` (`status`),
  INDEX `idx_pricereq_date`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- FR-11 : Notifications
--
--   The audit trail records everything that happened; this table records the
--   handful of things somebody needs to ACT on, addressed to the person who
--   has to act. One row is one notice for one account, so "read" means read
--   by that person and not by whoever opened it first.
--
--   Types:
--     stock_low       an item fell to or below its reorder level
--     stock_out       an item sold out completely
--     price_request   a price change is waiting for a decision
--     price_decision  the requester is told the outcome
--     discount        a discount was authorised on a sale
--     daily_income    end-of-day takings summary for the office
-- ---------------------------------------------------------------------
CREATE TABLE `discount_requests` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `requested_by`  INT           NOT NULL,
  -- A percentage, not a peso figure: it survives the cart changing.
  `percent`       DECIMAL(5,2)  NOT NULL,
  -- The cart total when the ask was made, for the approver's context only.
  `cart_total`    DECIMAL(12,2) NOT NULL DEFAULT 0,
  `reason`        VARCHAR(250)  DEFAULT NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `decided_by`    INT           DEFAULT NULL,
  `decision_note` VARCHAR(250)  DEFAULT NULL,
  `decided_at`    DATETIME      DEFAULT NULL,
  -- Set once the approval has been spent, so it cannot be reused.
  `sale_id`       INT           DEFAULT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_dreq_user`   FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_dreq_judge`  FOREIGN KEY (`decided_by`)   REFERENCES `users`(`id`),
  CONSTRAINT `fk_dreq_sale`   FOREIGN KEY (`sale_id`)      REFERENCES `sales`(`id`),
  INDEX `idx_dreq_status` (`status`, `created_at`),
  INDEX `idx_dreq_asker`  (`requested_by`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notifications` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT          NOT NULL,
  `type`        VARCHAR(30)  NOT NULL,
  `severity`    ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `title`       VARCHAR(140) NOT NULL,
  `body`        VARCHAR(255) DEFAULT NULL,
  `link_page`   VARCHAR(40)  DEFAULT NULL,
  `link_params` VARCHAR(120) DEFAULT NULL,
  -- Lets a repeat event update its own notice instead of stacking duplicates.
  `dedupe_key`  VARCHAR(80)  DEFAULT NULL,
  `read_at`     DATETIME     DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`)
      REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_notif_user`   (`user_id`, `read_at`),
  INDEX `idx_notif_date`   (`created_at`),
  UNIQUE KEY `uq_notif_dedupe` (`user_id`, `dedupe_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- FR-08 : Audit trail
-- ---------------------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT DEFAULT NULL,
  `username`    VARCHAR(60)  DEFAULT NULL,
  `module`      VARCHAR(40)  NOT NULL,
  `action`      VARCHAR(40)  NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- System settings (reorder defaults, store header for receipts)
-- ---------------------------------------------------------------------
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(60) PRIMARY KEY,
  `setting_value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`setting_key`,`setting_value`) VALUES
  ('store_name',    'A&A Household Appliances Trading'),
  ('store_address', 'Panabo City, Davao del Norte'),
  ('store_contact', '0917-000-0000'),
  ('currency',      'PHP'),
  ('default_reorder_level', '3'),
  ('forecast_window_days',  '90');

-- ===================================================================
--  PHASE 1 - transaction and reporting correctness
--  2 October 2026
--
--  Every statement here is ADDITIVE and IDEMPOTENT. Nothing is
--  dropped, nothing is rewritten, and running it twice is harmless.
--  MariaDB's "IF NOT EXISTS" on ALTER is what makes the re-run safe.
--
--  BACK UP FIRST (phpMyAdmin > aa_inventory > Export), then run this
--  from phpMyAdmin's SQL tab against aa_inventory.
--
--  TO ROLL BACK: restore that export. The new columns are nullable or
--  defaulted, so the OLD code also runs unchanged against this schema
--  - you can revert the PHP alone without touching the database.
-- ===================================================================

-- -------------------------------------------------------------------
-- 1. Concurrency-safe invoice numbers
--
--    The old code did COUNT(*) + 1 and probed for a free number. Two
--    tills reached the same number because neither row was committed
--    yet, and deleting any sale made the counter go backwards.
--
--    This table is bumped atomically inside the sale transaction with
--    LAST_INSERT_ID(), so two tills can never be handed one number.
-- -------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoice_counters` (
  `year`    INT NOT NULL PRIMARY KEY,
  `next_no` INT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed from the HIGHEST sequence already issued, never from a count,
-- so a previously deleted sale cannot cause a number to be reused.
INSERT INTO `invoice_counters` (`year`, `next_no`)
SELECT y, mx + 1 FROM (
    SELECT CAST(SUBSTRING(`invoice_no`, 5, 4) AS UNSIGNED) AS y,
           MAX(CAST(SUBSTRING(`invoice_no`, 9) AS UNSIGNED)) AS mx
    FROM `sales`
    WHERE `invoice_no` LIKE 'INV-%'
    GROUP BY y
) seed
ON DUPLICATE KEY UPDATE `next_no` = GREATEST(`invoice_counters`.`next_no`, VALUES(`next_no`));

-- Make sure the current year exists even in an empty database.
INSERT IGNORE INTO `invoice_counters` (`year`, `next_no`) VALUES (YEAR(CURDATE()), 1);

-- -------------------------------------------------------------------
-- 2. Tie a discount approval to ONE exact sale draft
--
--    An approval used to belong to the cashier, so it applied to
--    whatever was in the cart at checkout. A 10% approval on a 850
--    peso cart could be spent on a 50,000 peso one.
--
--    cart_fingerprint is a hash of the exact pieces and their prices.
--    If the cart changes, the hash changes and the approval no longer
--    matches, so it cannot be applied.
-- -------------------------------------------------------------------
ALTER TABLE `discount_requests`
  ADD COLUMN IF NOT EXISTS `cart_fingerprint` CHAR(64) NULL AFTER `cart_total`,
  ADD COLUMN IF NOT EXISTS `expires_at`       DATETIME  NULL AFTER `decided_at`,
  ADD COLUMN IF NOT EXISTS `consumed_at`      DATETIME  NULL AFTER `sale_id`;

-- Keep decisions in history rather than deleting them: an approval
-- that has been spent becomes 'used', a withdrawn one 'cancelled',
-- one that timed out 'expired'.
ALTER TABLE `discount_requests`
  MODIFY COLUMN `status`
    ENUM('pending','approved','rejected','cancelled','expired','used')
    NOT NULL DEFAULT 'pending';

-- -------------------------------------------------------------------
-- 3. Historical snapshots on each sold line
--
--    sale_items kept no cost and no category, and reports resolved the
--    category live through products. Re-categorising an item therefore
--    rewrote last month's report.
--
--    These columns are NULL for everything sold before today, and that
--    is deliberate: the real historical cost and category are NOT
--    recoverable and must not be invented. Reports must show those rows
--    as "not captured" rather than borrowing today's values.
-- -------------------------------------------------------------------
ALTER TABLE `sale_items`
  ADD COLUMN IF NOT EXISTS `category_at_sale`   VARCHAR(80)   NULL AFTER `sku`,
  ADD COLUMN IF NOT EXISTS `cost_price_at_sale` DECIMAL(12,2) NULL AFTER `unit_price`,
  -- the share of the sale's discount carried by this line, so net
  -- revenue can be reported per item and per category and still add up
  ADD COLUMN IF NOT EXISTS `discount_share`     DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `line_total`;

-- -------------------------------------------------------------------
-- 4. One sale per submission
--
--    Checkout had no idempotency key; a double click was stopped only
--    as a side effect of the piece lock, and reported a confusing
--    "already sold" error. The unique key makes the second attempt
--    resolve to the sale the first one created.
-- -------------------------------------------------------------------
ALTER TABLE `sales`
  ADD COLUMN IF NOT EXISTS `idempotency_key` CHAR(36) NULL;

-- A unique index cannot take IF NOT EXISTS portably; this is wrapped so
-- a re-run does not error.
SET @ix := (SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales'
              AND INDEX_NAME = 'uq_sales_idempotency');
SET @sql := IF(@ix = 0,
  'ALTER TABLE `sales` ADD UNIQUE KEY `uq_sales_idempotency` (`idempotency_key`)',
  'DO 0');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
