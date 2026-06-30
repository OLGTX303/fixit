-- Order-history timestamps on Job. created_at = submit/pending time; the rest
-- are stamped once when the booking first reaches that status (see BookingModel).
-- Payment time comes from StripePayment.created_at (not duplicated here).
-- Idempotent: guarded via information_schema so re-running is safe.
USE fixit;

SET @cols = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Job' AND COLUMN_NAME = 'created_at'
);
SET @sql = IF(@cols = 0,
  'ALTER TABLE Job
     ADD COLUMN created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     ADD COLUMN accepted_at    DATETIME NULL,
     ADD COLUMN in_progress_at DATETIME NULL,
     ADD COLUMN completed_at   DATETIME NULL,
     ADD COLUMN cancelled_at   DATETIME NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
