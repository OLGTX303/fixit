-- Migration: Stripe test-mode saved payment methods (no raw card data)
USE fixit;

ALTER TABLE User
  ADD COLUMN stripe_test_customer_id VARCHAR(255) NULL,
  ADD COLUMN stripe_test_default_payment_method_id VARCHAR(255) NULL,
  ADD COLUMN stripe_test_payment_method_last4 VARCHAR(4) NULL,
  ADD COLUMN stripe_test_payment_method_brand VARCHAR(32) NULL,
  ADD COLUMN stripe_test_payment_method_created_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS StripePayment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  booking_id INT NULL,
  stripe_payment_intent_id VARCHAR(255) NOT NULL,
  stripe_setup_intent_id VARCHAR(255) NULL,
  amount_cents INT NOT NULL DEFAULT 0,
  currency VARCHAR(3) NOT NULL DEFAULT 'usd',
  status VARCHAR(40) NOT NULL DEFAULT 'pending',
  failure_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_stripe_payment_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
  CONSTRAINT fk_stripe_payment_booking FOREIGN KEY (booking_id) REFERENCES Job(id) ON DELETE SET NULL,
  UNIQUE KEY uq_stripe_pi (stripe_payment_intent_id)
) ENGINE=InnoDB;

CREATE INDEX idx_stripe_payment_user ON StripePayment(user_id);