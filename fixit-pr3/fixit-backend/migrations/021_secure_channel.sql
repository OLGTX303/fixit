-- Dynamic per-interaction encryption (v2 skill): session + replay-nonce stores.
-- enc_session: one row per X25519 handshake; holds the derived master/mac keys
-- (server-side only, in DB so all instances share them) with a short TTL.
CREATE TABLE IF NOT EXISTS enc_session (
  session_id    VARCHAR(64)  NOT NULL PRIMARY KEY,
  user_id       INT          NOT NULL,
  master_secret VARBINARY(32) NOT NULL,
  mac_key       VARBINARY(32) NOT NULL,
  salt          VARBINARY(32) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    DATETIME     NOT NULL,
  KEY idx_enc_session_user (user_id),
  KEY idx_enc_session_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- enc_nonce: replay cache. Atomic insert (PK collision = replay). TTL >= window.
CREATE TABLE IF NOT EXISTS enc_nonce (
  session_id VARCHAR(64) NOT NULL,
  nonce      VARCHAR(64) NOT NULL,
  expires_at DATETIME    NOT NULL,
  PRIMARY KEY (session_id, nonce),
  KEY idx_enc_nonce_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
