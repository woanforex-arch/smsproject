#!/usr/bin/env bash
###############################################################################
#  Event Tickets — VPS deploy script (Ubuntu 22.04 / 24.04)
#
#  Usage (first run):
#     sudo bash deploy/deploy.sh
#
#  Subsequent deploys:
#     bash deploy/deploy.sh --update
###############################################################################
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/event-tickets}"
APP_USER="${APP_USER:-www-data}"
PHP_VERSION="${PHP_VERSION:-8.3}"
DOMAIN="${DOMAIN:-example.com}"
RUN_UPDATE="${1:-}"

log() { echo -e "\n\033[1;32m==> $*\033[0m\n"; }

# ---------------------------------------------------------------------------
# First-time provisioning
# ---------------------------------------------------------------------------
provision() {
    log "Installing system packages"
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq \
        nginx \
        mysql-server \
        curl unzip git \
        supervisor \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-mysql

    log "Configuring PHP"
    cp deploy/php-production.ini /etc/php/${PHP_VERSION}/fpm/conf.d/99-event-tickets.ini
    cp deploy/php-production.ini /etc/php/${PHP_VERSION}/cli/conf.d/99-event-tickets.ini
    systemctl restart php${PHP_VERSION}-fpm

    log "Creating application directory at ${APP_DIR}"
    mkdir -p "${APP_DIR}"
    chown -R "${APP_USER}:${APP_USER}" "${APP_DIR}"

    log "Installing Composer"
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

    log "Configuring Nginx"
    cp deploy/nginx.conf "/etc/nginx/sites-available/event-tickets"
    ln -sf /etc/nginx/sites-available/event-tickets /etc/nginx/sites-enabled/event-tickets
    rm -f /etc/nginx/sites-enabled/default
    sed -i "s/__DOMAIN__/${DOMAIN}/g" /etc/nginx/sites-available/event-tickets
    nginx -t
    systemctl reload nginx

    log "Configuring Supervisor"
    cp deploy/supervisor.conf /etc/supervisor/conf.d/event-tickets.conf
    sed -i "s#__APP_DIR__#${APP_DIR}#g" /etc/supervisor/conf.d/event-tickets.conf
    cp deploy/event-tickets-backup /usr/local/bin/event-tickets-backup
    chmod +x /usr/local/bin/event-tickets-backup
    sed -i "s#__APP_DIR__#${APP_DIR}#g" /usr/local/bin/event-tickets-backup

    systemctl supervisorctl reread
    systemctl supervisorctl update

    log "Installing cron for backups and scheduled jobs"
    crontab -u "${APP_USER}" deploy/crontab 2>/dev/null || true

    cat <<EOF

Setup complete. Next:
  1. cd ${APP_DIR} && git clone <your-repo> .
  2. composer install --no-dev --optimize-autoloader
  3. cp .env.example .env && php artisan key:generate
  4. Edit .env (DB credentials, Twilio keys, APP_URL)
  5. php artisan migrate --force && php artisan db:seed --force
  6. npm ci && npm run build && php artisan storage:link
  7. sudo supervisorctl restart event-tickets:*
EOF
}

# ---------------------------------------------------------------------------
# Code update
# ---------------------------------------------------------------------------
update() {
    log "Pulling latest code"
    cd "${APP_DIR}"
    git pull --ff-only

    log "Installing dependencies"
    composer install --no-dev --optimize-autoloader --no-interaction

    log "Building front-end assets"
    npm ci
    npm run build

    log "Clearing caches"
    php artisan optimize:clear

    log "Running migrations"
    php artisan migrate --force

    log "Optimising for production"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    log "Restarting queue workers"
    supervisorctl restart event-tickets:*

    log "Deployment complete."
}

if [[ "${RUN_UPDATE}" == "--update" ]]; then
    update
else
    provision
fi