-- Customer favourite providers (heart toggle + list view).
USE fixit;

CREATE TABLE IF NOT EXISTS Favorite (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_favorite_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
  CONSTRAINT fk_favorite_provider FOREIGN KEY (provider_id) REFERENCES ProviderProfile(id) ON DELETE CASCADE,
  UNIQUE KEY uq_favorite_user_provider (user_id, provider_id),
  INDEX idx_favorite_user (user_id, created_at DESC)
) ENGINE=InnoDB;