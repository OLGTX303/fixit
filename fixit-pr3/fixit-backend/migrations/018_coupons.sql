-- Coupon system + Job discount columns.
USE fixit;

CREATE TABLE IF NOT EXISTS Coupon (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL,
  scope ENUM('system','provider') NOT NULL,
  provider_id INT NULL,
  discount_type ENUM('percent','fixed') NOT NULL,
  discount_value DECIMAL(10,2) NOT NULL,
  min_spend DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_discount DECIMAL(10,2) NULL,
  usage_limit INT NULL,
  used_count INT NOT NULL DEFAULT 0,
  per_user_limit INT NOT NULL DEFAULT 1,
  starts_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_coupon_code (code),
  INDEX idx_coupon_scope_provider (scope, provider_id, is_active),
  INDEX idx_coupon_active_dates (is_active, starts_at, expires_at),
  CONSTRAINT fk_coupon_provider FOREIGN KEY (provider_id) REFERENCES ProviderProfile(id) ON DELETE CASCADE,
  CONSTRAINT fk_coupon_created_by FOREIGN KEY (created_by) REFERENCES User(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS CouponRedemption (
  id INT AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT NOT NULL,
  user_id INT NOT NULL,
  booking_id INT NOT NULL,
  amount_discounted DECIMAL(10,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_coupon_booking (coupon_id, booking_id),
  INDEX idx_redemption_user (user_id, coupon_id),
  CONSTRAINT fk_redemption_coupon FOREIGN KEY (coupon_id) REFERENCES Coupon(id),
  CONSTRAINT fk_redemption_user FOREIGN KEY (user_id) REFERENCES User(id),
  CONSTRAINT fk_redemption_booking FOREIGN KEY (booking_id) REFERENCES Job(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Job columns for applied coupon (idempotent).
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Job' AND COLUMN_NAME = 'coupon_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE Job ADD COLUMN coupon_id INT NULL AFTER total, ADD COLUMN discount_amount DECIMAL(10,2) NULL DEFAULT NULL AFTER coupon_id',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Job' AND CONSTRAINT_NAME = 'fk_job_coupon'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE Job ADD CONSTRAINT fk_job_coupon FOREIGN KEY (coupon_id) REFERENCES Coupon(id)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;