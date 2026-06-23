-- Admin user blocking. UserModel selects this column (login + admin user list),
-- so it must exist; without it login fails with "Unknown column 'is_blocked'".
ALTER TABLE User ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0;
