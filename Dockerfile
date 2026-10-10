FROM php:8.3-apache-bookworm

WORKDIR /var/www/html

# La imagen oficial compila pdo_sqlite, sqlite3 y mbstring. Fallar si faltan.
RUN php -r 'foreach (["pdo_sqlite", "sqlite3", "mbstring"] as $ext) { if (!extension_loaded($ext)) { fwrite(STDERR, "Missing extension: $ext\n"); exit(1); } }'

COPY app/ ./app/
COPY config/config.example.php ./config/config.example.php
COPY public/ ./public/
COPY views/ ./views/
COPY tests/ ./tests/
COPY tools/init-db.php ./tools/init-db.php
COPY database.sql ./database.sql
COPY docker/php.ini /usr/local/etc/php/conf.d/regexlab.ini
COPY docker/apache.conf /etc/apache2/regexlab-vhost.template
COPY docker/entrypoint.sh /usr/local/bin/regexlab-entrypoint

RUN sed -i 's/\r$//' /usr/local/bin/regexlab-entrypoint \
    && chmod +x /usr/local/bin/regexlab-entrypoint \
    && mkdir -p storage /var/lib/php/sessions \
    && chown -R www-data:www-data storage /var/lib/php/sessions \
    && chmod 0770 storage /var/lib/php/sessions

ENV PORT=10000
EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/regexlab-entrypoint"]
CMD ["apache2-foreground"]
