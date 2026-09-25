#!/bin/sh
set -e

# Render injecte la variable PORT ; en local (docker run sans -e PORT) on retombe sur 10000
PORT="${PORT:-10000}"

# Adapte Apache pour écouter sur ce port
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
