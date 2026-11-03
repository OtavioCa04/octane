#!/bin/sh
# Sobe o Apache na porta que a plataforma passar em $PORT (o Railway define sozinho; 8080 fora dele).
set -e

PORTA="${PORT:-8080}"
sed -i "s/^Listen .*/Listen ${PORTA}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORTA}>/" /etc/apache2/sites-available/000-default.conf

# O volume chega como root; o Apache grava as fotos como www-data
mkdir -p /data/uploads
chown www-data:www-data /data/uploads

exec apache2-foreground
