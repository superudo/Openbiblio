# Base stage with common dependencies
FROM php:8.2-fpm AS base

RUN docker-php-ext-install mysqli pdo_mysql

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

# Development stage - uses volume mounts for live code updates
FROM base AS development
# No code copy - relies on volume mounts from docker-compose.yml

# Production stage - bundles application code into the image
FROM base AS production

# Copy application files (whitelist approach)
# Dependencies and static assets (rarely change - cache-friendly)
COPY --chown=www-data:www-data lib/ /var/www/html/lib/
COPY --chown=www-data:www-data font/ /var/www/html/font/
COPY --chown=www-data:www-data css/ /var/www/html/css/
COPY --chown=www-data:www-data js/ /var/www/html/js/
COPY --chown=www-data:www-data images/ /var/www/html/images/

# Locale and reports (change occasionally)
COPY --chown=www-data:www-data locale/ /var/www/html/locale/
COPY --chown=www-data:www-data layouts/ /var/www/html/layouts/
COPY --chown=www-data:www-data reports/ /var/www/html/reports/

# Core application code (changes more frequently)
COPY --chown=www-data:www-data classes/ /var/www/html/classes/
COPY --chown=www-data:www-data functions/ /var/www/html/functions/
COPY --chown=www-data:www-data shared/ /var/www/html/shared/

# Feature modules (change most frequently during development)
COPY --chown=www-data:www-data admin/ /var/www/html/admin/
COPY --chown=www-data:www-data catalog/ /var/www/html/catalog/
COPY --chown=www-data:www-data circ/ /var/www/html/circ/
COPY --chown=www-data:www-data opac/ /var/www/html/opac/
COPY --chown=www-data:www-data home/ /var/www/html/home/
COPY --chown=www-data:www-data install/ /var/www/html/install/

# Root-level files
COPY --chown=www-data:www-data index.php database_constants.php /var/www/html/
COPY --chown=www-data:www-data COPYRIGHT.html GPL.txt ChangeLog README.md /var/www/html/
COPY --chown=www-data:www-data install_instructions_*.html /var/www/html/
