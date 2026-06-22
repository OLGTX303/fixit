-- Migration: real wallet ledger. Balance is DERIVED from this table
-- (SUM of signed settled rows) — no separate balance column to drift.
-- topup  = real Stripe PaymentIntent (pi_...) credit (+)
-- withdraw = real Stripe Refund (re_...) debit (-)
USE fixit;

CREATE TABLE IF NOT EXISTS WalletTransaction (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  kind VARCHAR(20) NOT NULL,               -- 'topup' | 'withdraw'
  amount_cents INT NOT NULL,               -- signed: + credit, - debit
  currency VARCHAR(3) NOT NULL DEFAULT 'myr',
  stripe_ref VARCHAR(255) NULL,            -- pi_... (topup) or re_... (withdraw)
  status VARCHAR(20) NOT NULL DEFAULT 'settled',  -- only 'settled' counts toward balance
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_tx_user FOREIGN KEY (user_id) REFERENCES User(id) ON DELETE CASCADE,
  INDEX idx_wallet_tx_user (user_id, created_at)
) ENGINE=InnoDB;
