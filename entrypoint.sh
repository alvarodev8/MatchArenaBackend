#!/bin/sh
set -e

# Ajustar permisos dinámicamente
chown -R www-data:www-data /var/www/storage
chown -R www-data:www-data /var/www/bootstrap/cache
chmod -R 775 /var/www/storage
chmod -R 775 /var/www/bootstrap/cache

# Iniciar PHP-FPM
exec php-fpm