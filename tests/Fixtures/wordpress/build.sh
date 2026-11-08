#!/usr/bin/env bash
#
# Builds the fixture site with a real WordPress installation and exports it
# to site.sql and uploads/. Needs Docker and network access.
#
#   tests/Fixtures/wordpress/build.sh
#
set -euo pipefail

cd "$(dirname "$0")"

WP_VERSION="${WP_VERSION:-7.1.4}"
NETWORK=wp-migrator-fixture
DATABASE=wp-migrator-fixture-db
VOLUME=wp-migrator-fixture-html

cleanup() {
    docker rm -f "$DATABASE" >/dev/null 2>&1 || true
    docker volume rm "$VOLUME" >/dev/null 2>&1 || true
    docker network rm "$NETWORK" >/dev/null 2>&1 || true
}

cleanup
trap cleanup EXIT

docker network create "$NETWORK" >/dev/null
docker volume create "$VOLUME" >/dev/null
docker run -d --name "$DATABASE" --network "$NETWORK" \
    -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=wordpress mysql:8.4 >/dev/null

until docker exec "$DATABASE" mysql -uroot -proot -e 'select 1' wordpress >/dev/null 2>&1; do
    sleep 2
done

wp() {
    docker run --rm --network "$NETWORK" --user 0 \
        -v "$VOLUME":/var/www/html -v "$PWD":/fixture \
        -e WP_CLI_PHP_ARGS='-d memory_limit=512M' \
        wordpress:cli php -d memory_limit=512M /usr/local/bin/wp --allow-root "$@"
}

wp core download --version="$WP_VERSION" --locale=en_US
wp config create --dbname=wordpress --dbuser=root --dbpass=root --dbhost="$DATABASE" --dbprefix=wp_ --skip-check
wp core install --url=https://old.example.com --title="Acme" \
    --admin_user=admin --admin_password=admin-password --admin_email=admin@example.com --skip-email
wp theme install twentytwentyone --activate
# Secure Custom Fields is the ACF fork on wordpress.org. Unlike the free ACF,
# it has repeaters and flexible content, and it stores them the same way.
wp plugin install secure-custom-fields --activate
wp eval-file /fixture/content.php
wp transient delete --all
docker exec "$DATABASE" mysqldump -uroot -proot --single-transaction --no-tablespaces --skip-dump-date \
    --default-character-set=utf8mb4 wordpress 2>/dev/null > site.sql

rm -rf uploads
docker run --rm -v "$VOLUME":/var/www/html -v "$PWD":/fixture alpine \
    sh -c 'cp -r /var/www/html/wp-content/uploads /fixture/uploads && chmod -R a+rX /fixture/uploads'

echo "Fixture site exported to $(pwd)/site.sql and $(pwd)/uploads"
