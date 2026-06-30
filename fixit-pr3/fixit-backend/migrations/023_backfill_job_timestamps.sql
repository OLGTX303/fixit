-- Backfill order-history timestamps for bookings that predate migration 022
-- (their status was reached before the columns existed, so they were never
-- stamped — a completed order showed its earlier steps as "pending").
-- Times are derived from scheduled_at so each order stays internally ordered:
--   created < accepted < in_progress < scheduled < completed.
-- Only fills NULLs (and fixes implausible created_at stamped at the 022 run),
-- so real timestamps from updateStatus going forward are preserved.
-- FIELD(status, ...) returns 0 when the status hasn't reached that step.
USE fixit;

-- Submit time: before the scheduled slot. Fix rows 022 stamped to "now"
-- (created_at after scheduled_at is impossible for a real order).
UPDATE Job
SET created_at = scheduled_at - INTERVAL 2 DAY
WHERE status <> 'inquiry'
  AND (created_at IS NULL OR created_at > scheduled_at);

UPDATE Job
SET accepted_at = scheduled_at - INTERVAL 1 DAY
WHERE accepted_at IS NULL
  AND FIELD(status, 'accepted', 'in_progress', 'completed', 'reviewed') > 0;

UPDATE Job
SET in_progress_at = scheduled_at
WHERE in_progress_at IS NULL
  AND FIELD(status, 'in_progress', 'completed', 'reviewed') > 0;

UPDATE Job
SET completed_at = scheduled_at + INTERVAL 2 HOUR
WHERE completed_at IS NULL
  AND FIELD(status, 'completed', 'reviewed') > 0;

UPDATE Job
SET cancelled_at = scheduled_at - INTERVAL 1 DAY
WHERE cancelled_at IS NULL
  AND status = 'cancelled';
