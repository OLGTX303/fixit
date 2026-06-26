-- User GPS + West Malaysia region (Johor Bahru, Skudai/UTM, etc.)
ALTER TABLE User
  ADD COLUMN location_label VARCHAR(120) NULL AFTER phone,
  ADD COLUMN region VARCHAR(32) NULL AFTER location_label,
  ADD COLUMN latitude DECIMAL(10,8) NULL AFTER region,
  ADD COLUMN longitude DECIMAL(11,8) NULL AFTER latitude;