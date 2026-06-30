-- System messages for booking-created chat separators.
ALTER TABLE Message ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER is_encrypted;
