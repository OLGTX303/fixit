-- Reviews can include photos uploaded by the customer. Stored as a JSON array
-- of image URLs (served via the /api/images proxy).
ALTER TABLE Review ADD COLUMN image_urls TEXT NULL;
