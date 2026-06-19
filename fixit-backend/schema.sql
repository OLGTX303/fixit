-- FixIt PR3 — MySQL schema
CREATE DATABASE IF NOT EXISTS fixit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fixit;

CREATE TABLE User (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','provider','admin') NOT NULL,
  phone VARCHAR(32) NULL
) ENGINE=InnoDB;

CREATE TABLE ServiceCategory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  icon_url VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE ProviderProfile (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  bio TEXT NOT NULL,
  location VARCHAR(180) NOT NULL,
  base_rate DECIMAL(10,2) NOT NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  kyc_doc_url VARCHAR(255) NULL,
  kyc_status ENUM('none','id_pending','id_passed','liveness_pending','submitted','failed') NOT NULL DEFAULT 'none',
  kyc_id_type VARCHAR(40) NULL,
  kyc_id_confidence DECIMAL(5,2) NULL,
  kyc_id_checks JSON NULL,
  kyc_liveness_passed TINYINT(1) NOT NULL DEFAULT 0,
  kyc_liveness_score DECIMAL(5,2) NULL,
  kyc_color_sequence_hash VARCHAR(64) NULL,
  kyc_liveness_checks JSON NULL,
  kyc_submitted_at DATETIME NULL,
  avg_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
  latitude DECIMAL(10,8) NOT NULL,
  longitude DECIMAL(11,8) NOT NULL,
  services_json JSON NULL,
  CONSTRAINT fk_provider_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ProviderCategory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  provider_id INT NOT NULL,
  category_id INT NOT NULL,
  UNIQUE KEY uq_provider_category (provider_id, category_id),
  CONSTRAINT fk_pc_provider FOREIGN KEY (provider_id) REFERENCES ProviderProfile(id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES ServiceCategory(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Job (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  provider_id INT NOT NULL,
  category_id INT NOT NULL,
  status ENUM('requested','accepted','in_progress','completed','reviewed') NOT NULL DEFAULT 'requested',
  scheduled_at DATETIME NOT NULL,
  address VARCHAR(255) NOT NULL,
  total DECIMAL(10,2) NULL,
  notes TEXT NULL,
  CONSTRAINT fk_job_customer FOREIGN KEY (customer_id) REFERENCES User(id),
  CONSTRAINT fk_job_provider FOREIGN KEY (provider_id) REFERENCES ProviderProfile(id),
  CONSTRAINT fk_job_category FOREIGN KEY (category_id) REFERENCES ServiceCategory(id)
) ENGINE=InnoDB;

CREATE TABLE Review (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_review_job FOREIGN KEY (job_id) REFERENCES Job(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Message (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  sender_id INT NOT NULL,
  body TEXT NULL,
  ciphertext TEXT NULL,
  iv VARCHAR(48) NULL,
  is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
  harm_status ENUM('clear','flagged','blocked') NOT NULL DEFAULT 'clear',
  harm_categories JSON NULL,
  content_hash VARCHAR(64) NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_message_job FOREIGN KEY (job_id) REFERENCES Job(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES User(id)
) ENGINE=InnoDB;

CREATE TABLE UserCrypto (
  user_id INT PRIMARY KEY,
  pin_salt VARCHAR(64) NOT NULL,
  pin_verifier VARCHAR(128) NOT NULL,
  public_key_jwk JSON NOT NULL,
  wrapped_private_key TEXT NOT NULL,
  private_key_iv VARCHAR(48) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usercrypto_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE JobCryptoKey (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  user_id INT NOT NULL,
  encrypted_job_key TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_job_user (job_id, user_id),
  CONSTRAINT fk_jobkey_job FOREIGN KEY (job_id) REFERENCES Job(id) ON DELETE CASCADE,
  CONSTRAINT fk_jobkey_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE HarmMessageReview (
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

CREATE INDEX idx_provider_verified ON ProviderProfile(is_verified);
CREATE INDEX idx_job_customer ON Job(customer_id);
CREATE INDEX idx_job_provider ON Job(provider_id);
CREATE INDEX idx_job_status ON Job(status);