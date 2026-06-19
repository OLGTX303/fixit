# FixIt Security Review

Review date: 2026-06-19. Scope: `fixit-backend/` + `fixit-frontend/`.

## Summary

| Area | Rating | Notes |
|------|--------|-------|
| SQL injection | **Pass** | PDO prepared statements only in all Models |
| Authentication | **Pass** (after fixes) | bcrypt, JWT, admin self-registration blocked |
| Authorization | **Pass** | RoleGuard + per-resource ownership checks |
| Input validation | **Pass** (after fixes) | Field validation, status enum whitelist |
| CORS | **Pass** (after fixes) | Explicit `CORS_ORIGIN` required; no wildcard default |
| Transport | **Deploy** | HTTPS required in production (configure at host) |
| Token storage | **Acceptable** | sessionStorage + short JWT TTL; httpOnly cookies recommended for v2 |
| Rate limiting | **Basic** | IP-based on auth endpoints (10/min); upgrade to Redis in scale |

## Issues found and fixed

### Critical (fixed)

1. **Admin self-registration** — `POST /auth/register` accepted `role: admin`. Now limited to `customer` and `provider`.
2. **HTML escaping stored in DB** — `htmlspecialchars` on write corrupted data and is not a substitute for parameterized queries. Replaced with `cleanText()` normalization.
3. **Permissive CORS default** — `*` fallback removed; server fails fast without `CORS_ORIGIN`.
4. **Weak boot secrets** — `JWT_SECRET` must be ≥32 chars; required DB env vars validated at startup.
5. **Mock user data in frontend build** — `public/mock/` removed so production builds do not ship seed credentials.

### High (fixed)

6. **Missing security headers** — Added `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cache-Control: no-store`.
7. **No auth rate limiting** — Added 10 attempts/minute per IP on login/register.
8. **Weak password policy** — Minimum 8 characters with letters and numbers.
9. **KYC filename injection** — `basename()` + character whitelist on uploaded filename metadata.
10. **Role guards on provider mutations** — `PUT/DELETE /providers/:id` now require provider or admin role at middleware level.

### Medium (documented / acceptable for demo)

11. **JWT in sessionStorage** — Vulnerable to XSS; Vue escapes template output by default. For hardened production, migrate to httpOnly secure cookies + CSRF token.
12. **No refresh token rotation** — 7-day JWT; reduce `JWT exp` in `AuthController::issueToken()` for production.
13. **File-based rate limit** — Works on single server; use Redis/Memcached behind load balancer.
14. **No audit logging** — Add structured logs for auth failures and admin actions in production.
15. **KYC upload is metadata-only** — No real file storage; implement virus scanning + private object storage before production KYC.

## Production checklist

### Backend (`fixit-backend`)

- [ ] Set `APP_DEBUG=false`
- [ ] Generate `JWT_SECRET` ≥ 32 random bytes (`openssl rand -base64 48`)
- [ ] Set `CORS_ORIGIN` to exact frontend URL(s), comma-separated if multiple
- [ ] MySQL user with least privilege (not root)
- [ ] TLS termination at reverse proxy (nginx/Caddy/Render)
- [ ] Import `schema.sql` + `seed.sql`; change all seed passwords
- [ ] Restrict `composer install --no-dev` in production
- [ ] Enable PHP `opcache` and disable `display_errors`

### Frontend (`fixit-frontend`)

- [ ] Set `VITE_API_URL` to HTTPS API URL at build time
- [ ] Serve `dist/` with CSP headers (see below)
- [ ] Never commit `.env` with production secrets
- [ ] Rebuild after any API URL change (Vite inlines env at build)

### Recommended CSP (static host)

```
Content-Security-Policy: default-src 'self'; connect-src 'self' https://your-api.example.com; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'
```

## E2E chat encryption

- Messages encrypted client-side with **AES-256-GCM** before upload
- Server stores **ciphertext only** — cannot read message bodies
- Each user has **RSA-2048** keypair; private key wrapped with **PIN-derived AES key** (PBKDF2, 310k iterations)
- Job AES keys distributed per participant via **RSA-OAEP**
- **New device**: user must enter PIN to unwrap private key and decrypt messages
- **Harm review**: client screens content pre-encrypt; flagged messages queue metadata (categories + SHA-256 hash) for admin — not plaintext

## Prepared statements (audit)

All queries use bound parameters in:

- `src/Models/UserModel.php`
- `src/Models/ProviderModel.php`
- `src/Models/BookingModel.php`
- `src/Models/ReviewModel.php`
- `src/Models/MessageModel.php`
- `src/Models/CategoryModel.php`

## Auth flow

```
Register/Login → bcrypt verify → JWT (HS256, 7d) → JwtAuth middleware → RoleGuard → Controller
```