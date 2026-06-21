-- Migration 006: stretch goals + spec gaps
--   • Provider availability calendar (stretch goal)
--   • Recurring bookings  (stretch goal)
--   • Tip on review       (should-have gap)
--   • Per-job rate        (spec gap)
--   • Priority listing    (CLO3 entrepreneurial angle)
USE fixit;

-- ─── Provider Availability Calendar ──────────────────────────────────────────
-- Stores which day/time windows the provider is open for bookings.
-- day_of_week: 0=Sun, 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat
CREATE TABLE IF NOT EXISTS ProviderAvailability (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  provider_id   INT  NOT NULL,
  day_of_week   TINYINT UNSIGNED NOT NULL COMMENT '0=Sun … 6=Sat',
  start_time    TIME NOT NULL,
  end_time      TIME NOT NULL,
  auto_confirm  TINYINT(1) NOT NULL DEFAULT 1
                  COMMENT 'If 1, bookings in this slot are auto-accepted',
  UNIQUE KEY uq_provider_slot (provider_id, day_of_week, start_time),
  CONSTRAINT fk_avail_provider
    FOREIGN KEY (provider_id) REFERENCES ProviderProfile(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Recurring Bookings ───────────────────────────────────────────────────────
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Job' AND COLUMN_NAME = 'recurrence_type') = 0,
  'ALTER TABLE Job ADD COLUMN recurrence_type ENUM(''none'',''weekly'',''biweekly'',''monthly'') NOT NULL DEFAULT ''none''',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Job' AND COLUMN_NAME = 'recurrence_end_date') = 0,
  'ALTER TABLE Job ADD COLUMN recurrence_end_date DATE NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── Tip on Review ───────────────────────────────────────────────────────────
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Review' AND COLUMN_NAME = 'tip_amount') = 0,
  'ALTER TABLE Review ADD COLUMN tip_amount DECIMAL(8,2) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─── Per-job Rate + Priority Listing on ProviderProfile ──────────────────────
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ProviderProfile' AND COLUMN_NAME = 'rate_type') = 0,
  'ALTER TABLE ProviderProfile ADD COLUMN rate_type ENUM(''hourly'',''per_job'') NOT NULL DEFAULT ''hourly''',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ProviderProfile' AND COLUMN_NAME = 'per_job_rate') = 0,
  'ALTER TABLE ProviderProfile ADD COLUMN per_job_rate DECIMAL(8,2) NULL DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ProviderProfile' AND COLUMN_NAME = 'is_priority') = 0,
  'ALTER TABLE ProviderProfile ADD COLUMN is_priority TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index for fast priority-first ordering in search
SET @sql := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ProviderProfile' AND INDEX_NAME = 'idx_provider_priority') = 0,
  'CREATE INDEX idx_provider_priority ON ProviderProfile(is_priority DESC, avg_rating DESC)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
