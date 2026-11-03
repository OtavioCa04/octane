# Octane num container só: site Angular + API PHP, servidos pelo Apache no mesmo endereço.
# Usado pelo Railway (railway.json), mas roda em qualquer lugar com Docker.
#
# As fotos enviadas ficam em /data/uploads: monte um volume nesse caminho, senão elas somem a cada deploy.
# Guia: deploy/RAILWAY.md

# 1) Build de produção do Angular
# Debian (glibc), não Alpine: alguns binários do build do Angular (lmdb) não têm versão para musl
FROM node:22-slim AS web
WORKDIR /web
COPY garagehub-web/package.json garagehub-web/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY garagehub-web/ ./
RUN npx ng build --configuration production

# 2) PHP + Apache
FROM php:8.3-apache

# curl, mbstring, openssl e zlib já vêm na imagem oficial; falta só o driver do MySQL
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers

COPY docker/php.ini /usr/local/etc/php/conf.d/octane.ini
COPY docker/apache.conf /etc/apache2/conf-available/octane.conf
RUN a2enconf octane

# Site: build do Angular + as mesmas regras do pacote de hospedagem comum (.htaccess, api/index.php)
COPY --from=web /web/dist/garagehub-web/browser/ /var/www/html/
COPY deploy/modelos/public_html/.htaccess /var/www/html/.htaccess
COPY deploy/modelos/public_html/api/index.php /var/www/html/api/index.php

# API fora da pasta pública; o manifesto das fotos dos modelos vai para o banco no pré-deploy
COPY garagehub-api/ /var/www/octane-api/
RUN mv /var/www/html/modelos/manifesto.json /var/www/octane-api/database/seeds/fotos-modelo.json \
 && mkdir -p /var/www/octane-api/storage \
 && chown -R www-data:www-data /var/www/octane-api/storage

COPY docker/iniciar.sh /usr/local/bin/iniciar-octane
RUN sed -i 's/\r$//' /usr/local/bin/iniciar-octane && chmod +x /usr/local/bin/iniciar-octane

# TRUST_PROXY: o Railway entrega os pedidos por um proxy; o IP real vem no X-Forwarded-For
ENV APP_ENV=production \
    TRUST_PROXY=1 \
    UPLOADS_DIR=/data/uploads \
    UPLOADS_URL=/uploads

CMD ["iniciar-octane"]
