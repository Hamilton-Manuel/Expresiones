#!/bin/sh
set -eu

PORT="${PORT:-10000}"
case "$PORT" in
    ''|*[!0-9]*) echo '[RegexLab] PORT debe ser numérico.' >&2; exit 1 ;;
esac
if [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo '[RegexLab] PORT debe estar entre 1 y 65535.' >&2
    exit 1
fi

printf 'Listen 0.0.0.0:%s\n' "$PORT" > /etc/apache2/ports.conf
sed "s/__PORT__/$PORT/g" /etc/apache2/regexlab-vhost.template > /etc/apache2/sites-available/000-default.conf
printf 'ServerName localhost\n' > /etc/apache2/conf-enabled/regexlab-servername.conf

mkdir -p /var/www/html/storage /var/lib/php/sessions
chown -R www-data:www-data /var/www/html/storage /var/lib/php/sessions
chmod 0770 /var/www/html/storage /var/lib/php/sessions
su -s /bin/sh www-data -c 'php /var/www/html/tools/init-db.php'

exec docker-php-entrypoint "$@"
