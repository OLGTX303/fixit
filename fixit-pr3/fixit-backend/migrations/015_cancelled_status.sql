-- Add 'cancelled' job status for customer-initiated cancellations.
USE fixit;

ALTER TABLE Job
  MODIFY status ENUM('inquiry','requested','accepted','in_progress','completed','reviewed','cancelled')
  NOT NULL DEFAULT 'requested';