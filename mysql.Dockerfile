# Immobilier CI — image MySQL pour Render (service privé "pserv")
# Le schéma complet (database.sql) est importé automatiquement au tout premier
# démarrage grâce au mécanisme d'initialisation officiel de l'image MySQL.
FROM mysql:8

ENV MYSQL_DATABASE=immobilier_ci

COPY database.sql /docker-entrypoint-initdb.d/01-schema.sql
