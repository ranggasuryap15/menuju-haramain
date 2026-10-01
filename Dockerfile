# ==============================================================================
# File: Dockerfile
# Tujuan: Definisi container environment PHP-FPM 8.2 untuk project Tabungan Umroh Menuju Haramain
# Dipakai Oleh: docker-compose.yml (service 'app')
# Dependensi Utama: php:8.2-fpm-alpine, Composer 2, ekstensi PHP (pdo_mysql, gd, zip, intl, opcache, bcmath)
# Daftar Fungsi/Instruksi Utama: Multi-stage extension install, user mapping (UID/GID 1000), PHP 8.2-FPM runtime
# Side Effect: File I/O saat build image, penyesuaian UID/GID user www-data untuk permission file
# ==============================================================================

FROM php:8.2-fpm-alpine

ARG UID=1000
ARG GID=1000

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    git \
    shadow \
    unzip \
    zip \
    mysql-client

# Install PHP extensions using official extension installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl \
    opcache

# Install Composer binary from official composer image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Synchronize www-data UID and GID with host user (default 1000:1000)
RUN usermod -u ${UID} www-data && groupmod -g ${GID} www-data

# Set working directory
WORKDIR /var/www/html

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]

