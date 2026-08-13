# Deployment Checklist — Douzloo SaaS

Laravel 13 · PHP 8.5 · MySQL/MariaDB · Redis (optional) · Sanctum API

---

## 1. Environment Variables

Copy `.env.example` to `.env` and set every value:

| Variable | Required | Example | Notes |
|---|---|---|---|
| `APP_ENV` | yes | `production` | Must be `production` for security defaults |
| `APP_KEY` | yes | (generate) | `php artisan key:generate` — never reuse the local key |
| `APP_DEBUG` | yes | `false` | Must be false in production |
| `APP_URL` | yes | `https://saas.example.com` | Must match the public origin |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` / `APP_FAKER_LOCALE` | no | `fa` / `en` / `fa_IR` | Persian-first UI |
| `DB_CONNECTION` | yes | `mysql` | Tested on MariaDB 11 / MySQL 8 |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | yes | — | Dedicated user with least privilege |
| `DB_SSLMODE` | no | `prefer` | Enable if DB is remote |
| `CACHE_STORE` | yes | `redis` or `database` | Redis recommended; `database` is the current default |
| `CACHE_PREFIX` | no | `douzloo` | Use a prefix when sharing Redis |
| `QUEUE_CONNECTION` | yes | `database` or `redis` | `sync` is only acceptable in dev |
| `SESSION_DRIVER` | yes | `database` or `redis` | Cookie-based auth (Sanctum stateful) |
| `SESSION_DOMAIN` | no | `.example.com` | Needed for subdomains |
| `SESSION_SECURE_COOKIE` | yes | `true` | HTTPS only |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` / `REDIS_DB` | if Redis | — | — |
| `MAIL_MAILER` | yes | `smtp` / `resend` / `postmark` | Use a real provider in prod |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | yes | `no-reply@…` | — |
| `FILESYSTEM_DISK` | yes | `public` or `s3` | Public downloads/PDFs; see Storage |
| `AWS_*` | if S3 | — | For S3-backed storage |
| `SANCTUM_STATEFUL_DOMAINS` | yes | `saas.example.com` | Frontend origin(s) for cookie auth |
| `FRONTEND_URL` | yes | `https://saas.example.com` | CORS origin |
| `LOG_CHANNEL` | yes | `stack` / `daily` | Rotate daily in prod |

**Generate:** `php artisan key:generate` (then set `APP_KEY` in `.env`).

---

## 2. Queue Setup

- Production queue driver: `QUEUE_CONNECTION=database` (already provisioned by the default migration) or `redis`.
- Create the queue tables (database driver):
  ```bash
  php artisan queue:table      # jobs, job_batches, failed_jobs
  php artisan migrate
  ```
  *Note:* the app currently has no queued jobs; the worker is still required for future mail/notifications and `failed_jobs` table is part of migrations.
- Run the worker (see Supervisor below).
- Failed jobs are stored in `failed_jobs` — monitor via `php artisan queue:failed`.

---

## 3. Redis Setup

Used for cache/sessions/queue when enabled:

```bash
# install + enable extension
apt-get install -y php-redis redis-server
# configure via .env
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

- If no Redis is available, keep `CACHE_STORE=database` and `QUEUE_CONNECTION=database` — fully supported.
- Restart PHP-FPM after installing the extension.

---

## 4. Storage Setup

```bash
php artisan storage:link     # public storage -> storage/app/public
```

- **Downloads** and **generated PDFs** are served from `storage/app/public/downloads` and `storage/app/public/pdf`.
- Ensure the web server can read/write `storage/` and `bootstrap/cache/`:
  ```bash
  chown -R www-data:www-data storage bootstrap/cache
  chmod -R 775 storage bootstrap/cache
  ```
- For scale-out, move to S3: set `FILESYSTEM_DISK=s3` + `AWS_*` variables, and update download storage paths accordingly.

---

## 5. Web Server (nginx) + SSL

### Install

```bash
apt-get install -y nginx certbot python3-certbot-nginx
```

### Site configuration

`/etc/nginx/sites-available/douzloo`:

```nginx
server {
    listen 80;
    server_name saas.example.com;

    # Redirect all HTTP to HTTPS (certbot can add this too)
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name saas.example.com;

    # --- SSL (replace with certbot-managed paths) ---
    ssl_certificate     /etc/letsencrypt/live/saas.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/saas.example.com/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    root /var/www/html/public;
    index index.php;

    client_max_body_size 25M;          # allows ticket attachments (10MB cap) + PDFs

    # Deny access to dotfiles & hidden dirs
    location ~ /\.(?!well-known).* { deny all; }

    # Backend route: OPTIONS must not be blocked by the auth middleware (Laravel handles CORS)
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Static assets straight from disk
    location ~* \.(?:css|js|jpg|jpeg|gif|png|svg|ico|woff2?)$ {
        expires 7d;
        access_log off;
        add_header Cache-Control "public, immutable";
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Enable and test:

```bash
ln -s /etc/nginx/sites-available/douzloo /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### SSL with Let's Encrypt

```bash
certbot --nginx -d saas.example.com
```

- Auto-renew via the bundled systemd timer: `systemctl status certbot.timer` (enable it).
- `SESSION_SECURE_COOKIE=true`, `FRONTEND_URL=https://saas.example.com`, and `SANCTUM_STATEFUL_DOMAINS=saas.example.com` must all use HTTPS URLs (see §1).

### Optional hardening headers (server block `add_header`)

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

---

## 7. Cron Jobs

Add the single Laravel scheduler line to crontab:

```bash
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

There is currently **no** registered scheduled task; the scheduler line is the hook for future tasks (e.g., marking overdue invoices, expiring licenses, reminder digests).

---

## 8. Supervisor Configuration

`/etc/supervisor/conf.d/douzloo-worker.conf`:

```ini
[program:douzloo-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/html
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

Reload:

```bash
supervisorctl reread
supervisorctl update
supervisorctl status douzloo-worker:*
```

---

## 9. Production Cache Commands

Run after every deploy (in order):

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> **Order matters:** `config:cache` first, then routes/views/events. Never run `route:cache` if routes depend on closures (this app uses controller routes — safe).

Clear (only when changing config, not on routine deploys):

```bash
php artisan optimize:clear
```

---

## 10. Backup Recommendations

- **Database** — nightly dump + 14-day retention:
  ```bash
  30 2 * * * mysqldump --single-transaction -u saas -p'***' saas | gzip > /backups/saas_$(date +\%F).sql.gz && find /backups -name 'saas_*.sql.gz' -mtime +14 -delete
  ```
- **Storage** — replicate `storage/app/public` to off-site/S3 (nightly `rclone sync` or rsync).
- **`.env`** — store encrypted in the team's secret manager (never in git).
- **Restore drill** — test restore at least once per quarter; document the exact `mysql < dump.sql` command.
- Enable **binary logging** on MySQL for point-in-time recovery.

---

## 11. Deploy Sequence (Summary)

1. Pull code, run `composer install --no-dev --optimize-autoloader`.
2. Set `.env` (prod values) + `APP_KEY`.
3. `php artisan migrate --force`.
4. `php artisan storage:link`.
5. Run the cache commands from §9.
6. Reload PHP-FPM: `sudo systemctl reload php8.5-fpm`.
7. Restart workers: `supervisorctl restart douzloo-worker:*`.
8. Smoke-test: `curl -I https://saas.example.com/up` and hit a couple of API endpoints.
