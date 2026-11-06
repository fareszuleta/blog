FROM php:8.3-apache

# PDO + SQLite are already included in the official PHP image.
# public/ is the web root; src/ sits outside it (not reachable by URL).
COPY public/ /var/www/html/
COPY src/ /var/www/src/

# The base image's docker-php.conf sets "DirectoryIndex index.php index.html".
# This file loads after it (alphabetically last in conf-enabled) and puts
# index.html first, so the static home page is served at "/" directly.
COPY apache/directoryindex.conf /etc/apache2/conf-enabled/zz-directoryindex.conf

# Data directory for SQLite. A mounted volume can override these permissions,
# so the entrypoint fixes ownership at every start.
RUN mkdir -p /var/www/data && chown -R www-data:www-data /var/www/data
ENV DATA_DIR=/var/www/data
VOLUME ["/var/www/data"]

COPY docker-entrypoint-blog.sh /usr/local/bin/docker-entrypoint-blog.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-blog.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint-blog.sh"]
CMD ["apache2-foreground"]
