FROM php:8.3-apache

# PDO + SQLite ya vienen en la imagen oficial de PHP.
# public/ es la raíz web; src/ queda fuera de ella (no es accesible por URL).
COPY public/ /var/www/html/
COPY src/ /var/www/src/

# Carpeta de datos (SQLite) escribible por Apache. Montar un volumen aquí para persistir.
RUN mkdir -p /var/www/data && chown -R www-data:www-data /var/www/data
ENV DATA_DIR=/var/www/data
VOLUME ["/var/www/data"]

EXPOSE 80
