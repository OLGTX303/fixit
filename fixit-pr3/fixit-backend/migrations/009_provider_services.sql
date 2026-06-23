-- Migration: rich provider service catalog (Grab/Meituan-style listings).
-- Replaces the flat services_json names with per-service price/photo/description.
USE fixit;

CREATE TABLE IF NOT EXISTS ProviderService (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  description VARCHAR(500) NULL,
  image_url VARCHAR(512) NULL,
  sku VARCHAR(40) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_psvc_provider FOREIGN KEY (provider_id) REFERENCES ProviderProfile(id) ON DELETE CASCADE,
  INDEX idx_psvc_provider (provider_id, sort_order, id)
) ENGINE=InnoDB;
