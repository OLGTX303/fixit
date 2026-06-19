# FixIt Backend

PHP Slim 4 REST API with MySQL, JWT auth, and role-based access control.

## Requirements

- PHP 8.1+
- Composer
- MySQL 8.0+ (local install or managed host — **no Docker required**)

## Setup

### 1. MySQL

```bash
mysql -u root -p < schema.sql
mysql -u root -p < seed.sql
```

Create a dedicated user:

```sql
CREATE USER 'fixit'@'localhost' IDENTIFIED BY 'strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON fixit.* TO 'fixit'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Environment

```bash
cp .env.example .env
# Edit DB_* and JWT_SECRET (≥32 chars)
```

### 3. Run

```bash
composer install
composer start   # http://localhost:8080/api/health
```

## Security

See `../SECURITY.md`. Never deploy with default `JWT_SECRET` or `APP_DEBUG=true`.

## API

Base URL: `/api`

Auth: `Authorization: Bearer <token>`

Import `../fixit-frontend/fixit.postman_collection.json` for full endpoint tests.