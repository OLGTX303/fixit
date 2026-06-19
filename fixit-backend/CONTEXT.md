# FixIt Backend — Domain glossary

## Entities (MySQL)

- **User** — `customer` | `provider` | `admin`; passwords bcrypt-hashed
- **ProviderProfile** — linked 1:1 to provider User; `is_verified` gates customer visibility
- **Job** — booking between customer and provider; includes `notes`, `status`, `scheduled_at`
- **Review** — customer rating on completed job
- **Message** — chat on a job between customer and provider participants

## Auth

- Public: `POST /auth/register` (customer/provider only), `POST /auth/login`, `GET /categories`, `GET /providers`
- Protected: JWT bearer; `RoleGuard` enforces admin/provider/customer routes
- Admin accounts are seed-only — not self-registerable

## Data access

- All SQL in `src/Models/` via PDO prepared statements only