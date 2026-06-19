-- Migration: E2E message encryption + harm review
USE fixit;

ALTER TABLE Message
  MODIFY body TEXT NULL,
  ADD COLUMN IF NOT EXISTS ciphertext TEXT NULL,
  ADD COLUMN IF NOT EXISTS iv VARCHAR(48) NULL,
  ADD COLUMN IF NOT EXISTS is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS harm_status ENUM('clear','flagged','blocked') NOT NULL DEFAULT 'clear',
  ADD COLUMN IF NOT EXISTS harm_categories JSON NULL,
  ADD COLUMN IF NOT EXISTS content_hash VARCHAR(64) NULL;

-- MySQL 8.0 may not support IF NOT EXISTS on ADD COLUMN — run manually if errors occur.

CREATE TABLE IF NOT EXISTS UserCrypto (
  user_id INT PRIMARY KEY,
  pin_salt VARCHAR(64) NOT NULL,
  pin_verifier VARCHAR(128) NOT NULL,
  public_key_jwk JSON NOT NULL,
  wrapped_private_key TEXT NOT NULL,
  private_key_iv VARCHAR(48) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usercrypto_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS JobCryptoKey (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  user_id INT NOT NULL,
  encrypted_job_key TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_job_user (job_id, user_id),
  CONSTRAINT fk_jobkey_job FOREIGN KEY (job_id) REFERENCES Job(id) ON DELETE CASCADE,
  CONSTRAINT fk_jobkey_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS HarmMessageReview (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message_id INT NOT NULL,
  job_id INT NOT NULL,
  sender_id INT NOT NULL,
  harm_status ENUM('flagged','blocked','reviewed_clear','reviewed_action') NOT NULL DEFAULT 'flagged',
  harm_categories JSON NULL,
  content_hash VARCHAR(64) NULL,
  admin_notes TEXT NULL,
  reviewed_by INT NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_harm_message FOREIGN KEY (message_id) REFERENCES Message(id) ON DELETE CASCADE,
  CONSTRAINT fk_harm_reviewer FOREIGN KEY (reviewed_by) REFERENCES User(id)
) ENGINE=InnoDB;