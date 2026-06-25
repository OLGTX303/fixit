-- Idempotent integrity constraints for reviews and wallet payouts.

SET @db := DATABASE();

-- Dedupe before the unique below: keep the earliest review per job, drop the
-- rest. Idempotent (no-op once clean). Required because pre-existing test data
-- had multiple Review rows per job, which blocked uq_review_job from applying.
DELETE r FROM Review r
JOIN Review keep ON keep.job_id = r.job_id AND keep.id < r.id;

-- One review per completed job.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'Review' AND index_name = 'uq_review_job'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE Review ADD UNIQUE KEY uq_review_job (job_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Prevent double provider payout for the same job.
SET @exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'WalletTransaction' AND index_name = 'uq_wallet_payout_job'
);
SET @sql := IF(@exists = 0,
    "ALTER TABLE WalletTransaction ADD UNIQUE KEY uq_wallet_payout_job (kind, stripe_ref)",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- One payment row per booking (prevents double-charge races).
SET @exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'StripePayment' AND index_name = 'uq_stripe_booking'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE StripePayment ADD UNIQUE KEY uq_stripe_booking (booking_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;