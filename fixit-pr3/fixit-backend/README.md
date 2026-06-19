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

### 3. Run (local dev only)

```bash
composer install
composer start   # http://localhost:8080/api/health
```

`composer start` runs PHP’s built-in server for development. **Do not use it in production.**

## Production — aaPanel / nginx + PHP-FPM

On a Linux host with [aaPanel](https://www.aapanel.com/), the API is served by **nginx → PHP-FPM → `public/index.php`**. There is no separate daemon to start.

### Paths

| Item | Path |
|------|------|
| Project root | `/www/wwwroot/fixit.olgtx.com/` |
| Web root (nginx `root`) | `/www/wwwroot/fixit.olgtx.com/public/` |
| Nginx vhost | `/www/server/panel/vhost/nginx/fixit.olgtx.com.conf` |
| PHP-FPM socket | `/tmp/php-cgi-85.sock` (PHP 8.5 on aaPanel) |
| MySQL socket (aaPanel) | `/tmp/mysql.sock` |

### 1. Upload and install

```bash
cd /www/wwwroot/fixit.olgtx.com
export COMPOSER_ALLOW_SUPERUSER=1
/usr/bin/composer install --no-dev --optimize-autoloader
cp .env.example .env
# Edit .env — see below
chown -R www:www .
chmod 640 .env
```

### 2. MySQL

```bash
mysql --socket=/tmp/mysql.sock -uroot -p <<'SQL'
CREATE DATABASE IF NOT EXISTS fixit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'fixit'@'localhost' IDENTIFIED BY 'your-strong-password';
GRANT ALL PRIVILEGES ON fixit.* TO 'fixit'@'localhost';
FLUSH PRIVILEGES;
SQL

mysql --socket=/tmp/mysql.sock -uroot -p --default-character-set=utf8mb4 fixit < schema.sql
mysql --socket=/tmp/mysql.sock -uroot -p --default-character-set=utf8mb4 fixit < seed.sql
```

Set in `.env`:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=fixit
DB_USER=fixit
DB_PASS=your-strong-password
JWT_SECRET=<random-64-char-hex>
CORS_ORIGIN=https://fixit.olgtx.com,http://fixit.olgtx.com,https://localhost,capacitor://localhost,http://localhost
APP_DEBUG=false
```

Generate secrets:

```bash
openssl rand -hex 32   # JWT_SECRET
openssl rand -hex 16   # DB_PASS
```

### 3. Nginx vhost (Slim front controller)

**Critical:** `root` must be `public/`, and all non-file requests must fall through to `index.php`.

```bash
cat > /www/server/panel/vhost/nginx/fixit.olgtx.com.conf << 'EOF'
server
{
    listen 80;
    listen [::]:80;
    server_name fixit.olgtx.com;
    index index.php index.html;
    root /www/wwwroot/fixit.olgtx.com/public;

    include /www/server/panel/vhost/nginx/extension/fixit.olgtx.com/*.conf;
    include /www/server/panel/vhost/nginx/well-known/fixit.olgtx.com.conf;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    include enable-php-85.conf;

    location = /favicon.ico {
        log_not_found off;
        access_log off;
        return 204;
    }

    location ~ ^/(\.user.ini|\.htaccess|\.git|\.env|\.svn|\.project|LICENSE|README.md) {
        return 404;
    }

    location /.well-known {
        allow all;
    }

    access_log /www/wwwlogs/fixit.olgtx.com.log;
    error_log /www/wwwlogs/fixit.olgtx.com.error.log;
}
EOF
```

### 4. aaPanel site settings

| Setting | Value |
|---------|-------|
| Domain | `fixit.olgtx.com` |
| Site root | `/www/wwwroot/fixit.olgtx.com/public` |
| PHP version | 8.5 |
| Site type | **PHP** (not Composer/Node project) |

If aaPanel recreates the site, re-check that **root ends in `/public`** and the `try_files` block above is still present. Wrong root causes nginx errors like:

```
open() "/www/wwwroot/fixit.olgtx.com/api/health" failed (2: No such file or directory)
```

### 5. Disable broken systemd service (if aaPanel created one)

aaPanel may create `fixit.olgtx.com.service` with `php composer start`, which fails with `Could not open input file: composer`. The API does **not** use this service.

```bash
systemctl stop fixit.olgtx.com.service
systemctl disable fixit.olgtx.com.service
systemctl reset-failed fixit.olgtx.com.service
```

### 6. Start / restart (production)

```bash
/etc/init.d/php-fpm-85 start    # or: restart
nginx -t && /etc/init.d/nginx reload
```

**Do not use on the server:**

- `composer start`
- `php composer start`
- `systemctl start fixit.olgtx.com.service`

### 7. PHP `zip.so` warning (optional)

If PHP logs `Unable to load dynamic library 'zip.so'`, either install the zip extension in aaPanel (PHP 8.5 → Extensions), or comment it out:

```bash
sed -i 's/^extension = zip.so/;extension = zip.so/' \
  /www/server/php/85/etc/php.ini \
  /www/server/php/85/etc/php-cli.ini
/etc/init.d/php-fpm-85 restart
```

FixIt does not require `zip` at runtime.

### 8. Verify

```bash
curl -H "Host: fixit.olgtx.com" http://127.0.0.1/api/health
curl -H "Host: fixit.olgtx.com" http://127.0.0.1/api/categories
curl -X POST -H "Host: fixit.olgtx.com" -H "Content-Type: application/json" \
  -d '{"email":"admin@fixit.com","password":"password123"}' \
  http://127.0.0.1/api/auth/login
```

Expected: `{"status":"ok"}`, category JSON, and a JWT `token`.

### Frontend API URL

Build the SPA with:

```bash
VITE_API_URL=https://fixit.olgtx.com/api npm run build
```

## Security

See `../SECURITY.md`. Never deploy with default `JWT_SECRET` or `APP_DEBUG=true`.

## API

Base URL: `/api`

Auth: `Authorization: Bearer <token>`

| Endpoint | Auth | Purpose |
|----------|------|---------|
| `GET /api/health` | — | Liveness check |
| `GET /api` | — | API index (`name`, `health` link) |
| `GET /api/categories` | — | Service categories |
| `POST /api/auth/login` | — | Login → JWT |

Use `GET /api/health` to verify the API is running.