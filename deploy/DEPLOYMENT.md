# Deploying to a VPS

Target: Ubuntu 22.04 / 24.04 with Nginx, PHP-FPM 8.3 and MySQL 8.

Everything below is automated by `deploy/deploy.sh`. This document explains what it does
so you can adapt or debug it.

---

## 1. Provision the server

```bash
git clone <your-repo> event-tickets
cd event-tickets

sudo APP_DIR=/var/www/event-tickets DOMAIN=tickets.example.com bash deploy/deploy.sh
```

The script will:

1. Install `nginx`, `mysql-server`, `php8.3-fpm` and extensions, `supervisor`, `git`, `unzip`
2. Drop `deploy/php-production.ini` into both the FPM and CLI pools and restart FPM
3. Create `/var/www/event-tickets` owned by `www-data`
4. Install Composer
5. Install the Nginx vhost (HTTPS section included but commented)
6. Install the Supervisor configs for `queue:work` and `schedule:work`
7. Install the nightly backup script to `/usr/local/bin/event-tickets-backup`
8. Install the crontab for `schedule:run` and the backup job

---

## 2. Install the application

```bash
cd /var/www/event-tickets
git clone <your-repo> .        # or rsync your working copy

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate

nano .env                      # set DB_*, APP_URL, MAIL_*, TWILIO_*
```

### `.env` essentials for production

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tickets.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=event_tickets
DB_USERNAME=event_tickets
DB_PASSWORD=<strong password>

MAIL_MAILER=smtp
MAIL_HOST=smtp.provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=tickets@example.com

TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...
TWILIO_FROM_NUMBER=+254...
```

---

## 3. Create the database user

```bash
mysql -u root
```

```sql
CREATE DATABASE event_tickets CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'event_tickets'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL PRIVILEGES ON event_tickets.* TO 'event_tickets'@'localhost';
FLUSH PRIVILEGES;
```

---

## 4. Finish setup

```bash
php artisan migrate --force
php artisan storage:link
php artisan optimize

# Create the first admin interactively, then delete the demo seeder usage:
php artisan db:seed --class=AdminUserSeeder --force   # optional demo accounts
```

---

## 5. Enable HTTPS

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tickets.example.com -d www.tickets.example.com
```

Certbot rewrites the vhost for you. The commented HTTPS block in `deploy/nginx.conf`
documents the equivalent manual configuration.

---

## 6. Verify the long-running processes

```bash
sudo supervisorctl status
```

```
event-tickets-worker:event-tickets-worker_00   RUNNING
event-tickets-worker:event-tickets-worker_01   RUNNING
event-tickets-worker:event-tickets-worker_02   RUNNING
event-tickets-scheduler:..._00                 RUNNING
```

---

## 7. Twilio webhook

In the Twilio console, set the messaging service's **Status Callback URL** to:

```
https://tickets.example.com/webhooks/twilio/status
```

This is already CSRF-exempt and returns `200` for every callback so Twilio never retries
a handled one.

---

## Subsequent deploys

```bash
cd /var/www/event-tickets
sudo bash deploy/deploy.sh --update
```

This pulls, installs dependencies, rebuilds assets, clears and re-warms caches, runs
migrations and restarts the queue workers.

---

## Important: MySQL/MariaDB `explicit_defaults_for_timestamp`

MySQL and MariaDB ship with `explicit_defaults_for_timestamp = OFF` by default. In that
mode the **first NOT NULL `TIMESTAMP` column in every table silently gains**

```sql
ON UPDATE CURRENT_TIMESTAMP
```

which means any update to that row rewrites the column to `NOW()`. That is exactly the
kind of bug that passes on SQLite and only appears in production.

This project already declares its OTP `expires_at` column as `NULL` to opt out, but you
should still enable the modern behaviour on your server:

```ini
# /etc/mysql/mariadb.conf.d/99-event-tickets.cnf
[mysqld]
explicit_defaults_for_timestamp = 1
```

```bash
sudo systemctl restart mysql
```

**Verify nothing in your schema picked up the legacy behaviour:**

```sql
SELECT TABLE_NAME, COLUMN_NAME, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'event_tickets'
  AND EXTRA LIKE '%on update%';
```

An empty result is what you want. Anything returned needs its migration changed to declare
the column `->nullable()`.

---

## Backups and restore

Backups run nightly at 02:15 and land in `/var/backups/event-tickets`:

- `db-YYYYmmdd-HHMMSS.sql.gz` — full dump with routines, triggers and events
- `files-YYYYmmdd-HHMMSS.tar.gz` — everything under `storage/app/public`

Retention: 14 days, then a sweep of anything older than 180 days.

**Test a restore before you need one:**

```bash
sudo APP_DIR=/var/www/event-tickets bash deploy/restore.sh /var/backups/event-tickets/db-20260101-020000.sql.gz
```

To copy backups off the server, uncomment the `rclone` lines at the bottom of
`deploy/event-tickets-backup` and configure `rclone`.

---

## Operational notes

**Always run `php artisan optimize:clear` when you change `.env` on the server.**
Configuration is cached; edits will not take effect otherwise.

**Queued mail** is handled by the Supervisor workers. If emails do not arrive:

```bash
sudo supervisorctl restart event-tickets:*
php artisan queue:failed       # inspect failures
```

**Locks and concurrent check-in** are handled in the database, so you can run multiple
scanner devices or staff tablets against one instance without double-admitting a guest.

**Rate limiting** uses the configured `CACHE_STORE`. With the default database store this
needs no extra service; if you move to Redis, set `REDIS_*` in `.env` and install the
`phpredis` extension.