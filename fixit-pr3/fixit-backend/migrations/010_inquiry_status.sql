-- Allow a pre-order "inquiry" conversation: a customer can message a provider
-- before placing an order. Inquiry jobs are excluded from booking/earnings/
-- request lists in the app and only surface in the messages/conversations views.
ALTER TABLE Job
  MODIFY status ENUM('inquiry','requested','accepted','in_progress','completed','reviewed')
  NOT NULL DEFAULT 'requested';
