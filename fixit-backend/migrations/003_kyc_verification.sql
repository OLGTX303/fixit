-- Migration: Government ID recognition + 8-color reflection liveness KYC
USE fixit;

ALTER TABLE ProviderProfile
  ADD COLUMN kyc_status ENUM(
    'none','id_pending','id_passed','liveness_pending','submitted','failed'
  ) NOT NULL DEFAULT 'none',
  ADD COLUMN kyc_id_type VARCHAR(40) NULL,
  ADD COLUMN kyc_id_confidence DECIMAL(5,2) NULL,
  ADD COLUMN kyc_id_checks JSON NULL,
  ADD COLUMN kyc_liveness_passed TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN kyc_liveness_score DECIMAL(5,2) NULL,
  ADD COLUMN kyc_color_sequence_hash VARCHAR(64) NULL,
  ADD COLUMN kyc_liveness_checks JSON NULL,
  ADD COLUMN kyc_submitted_at DATETIME NULL;