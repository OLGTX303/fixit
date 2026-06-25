-- Scale indexes for 20k+ user/provider lists (idempotent).
-- Verify with: EXPLAIN SELECT ... USING these columns.

SET @db = DATABASE();

-- User(role) — admin filters / role counts
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'User' AND INDEX_NAME = 'idx_user_role') = 0,
  'CREATE INDEX idx_user_role ON User(role)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- User(email) — login lookup (UNIQUE already indexes; explicit for EXPLAIN clarity)
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'User' AND INDEX_NAME = 'idx_user_email') = 0,
  'CREATE INDEX idx_user_email ON User(email)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ProviderCategory(provider_id, category_id) — category filter in search
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ProviderCategory' AND INDEX_NAME = 'idx_pc_provider_category') = 0,
  'CREATE INDEX idx_pc_provider_category ON ProviderCategory(provider_id, category_id)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ProviderProfile(is_verified) — may already exist as idx_provider_verified
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ProviderProfile' AND INDEX_NAME = 'idx_provider_verified') = 0,
  'CREATE INDEX idx_provider_verified ON ProviderProfile(is_verified)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Job(provider_id) / Job(customer_id) — booking lists
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'Job' AND INDEX_NAME = 'idx_job_provider') = 0,
  'CREATE INDEX idx_job_provider ON Job(provider_id)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'Job' AND INDEX_NAME = 'idx_job_customer') = 0,
  'CREATE INDEX idx_job_customer ON Job(customer_id)',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;