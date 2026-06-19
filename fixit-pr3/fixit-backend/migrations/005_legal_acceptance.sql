-- Migration: Terms of Service & Privacy Policy acceptance audit trail
USE fixit;

ALTER TABLE User
  ADD COLUMN terms_accepted_at DATETIME NULL,
  ADD COLUMN privacy_accepted_at DATETIME NULL,
  ADD COLUMN legal_policy_version VARCHAR(20) NULL;