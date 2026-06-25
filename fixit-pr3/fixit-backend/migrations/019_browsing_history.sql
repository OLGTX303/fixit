-- Recently viewed providers (browsing history).
USE fixit;

CREATE TABLE IF NOT EXISTS BrowsingHistory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider_id INT NOT NULL,
  viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_history_user_provider (user_id, provider_id),
  INDEX idx_history_user_viewed (user_id, viewed_at DESC),
  CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
  CONSTRAINT fk_history_provider FOREIGN KEY (provider_id) REFERENCES ProviderProfile(id) ON DELETE CASCADE
) ENGINE=InnoDB;