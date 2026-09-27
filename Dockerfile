# Immobilier CI — image de l'application web (PHP + Apache) pour Render
FROM php:8.2-apache

# Extensions PHP nécessaires (PDO MySQL + mysqli + curl pour l'upload vers Cloudinary)
RUN apt-get update && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli curl \
    && rm -rf /var/lib/apt/lists/*

# Relève les limites d'upload PHP (par défaut 2 Mo, trop bas pour des photos de smartphone)
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Apache : URLs sans .php (rewrite) + compression et cache navigateur pour les fichiers statiques (performance)
RUN a2enmod rewrite deflate expires headers
COPY docker/apache-overrides.conf /etc/apache2/conf-available/apache-overrides.conf
RUN a2enconf apache-overrides

# Copie du code source de l'application
COPY . /var/www/html/

# Le dossier d'upload doit être accessible en écriture par le serveur web
RUN mkdir -p /var/www/html/uploads/properties \
    && chown -R www-data:www-data /var/www/html/uploads

# Render fournit dynamiquement le port d'écoute via la variable $PORT.
# Ce script adapte la config Apache à ce port avant de démarrer.
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["/entrypoint.sh"]
