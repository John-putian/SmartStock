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
