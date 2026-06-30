-- Push targets for chat notifications. One row per device/browser.
--   web     → Web Push: endpoint + p256dh + auth keys (VAPID).
--   android → FCM: fcm_token.
-- dedupe_key is the endpoint (web) or token (android), so re-subscribing the
-- same device updates in place instead of piling up rows.
USE fixit;

CREATE TABLE IF NOT EXISTS PushSubscription (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  platform ENUM('web','android') NOT NULL,
  dedupe_key VARCHAR(191) NOT NULL,
  endpoint TEXT NULL,
  p256dh VARCHAR(255) NULL,
  auth VARCHAR(255) NULL,
  fcm_token VARCHAR(512) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_push_user_key (user_id, dedupe_key),
  INDEX idx_push_user (user_id),
  CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE
) ENGINE=InnoDB;
