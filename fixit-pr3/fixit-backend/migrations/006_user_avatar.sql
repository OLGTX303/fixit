-- Add avatar support to User. Avatars are stored in Cloudflare R2 and served
-- through the backend proxy (GET /api/avatars/{key}); this column holds the
-- ready-to-use URL.
ALTER TABLE User
  ADD COLUMN avatar_url VARCHAR(512) NULL AFTER phone;
