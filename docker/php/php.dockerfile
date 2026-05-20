FROM php:8.4-fpm-bullseye

ARG UID
ARG GID

ENV UID=${UID:-1000}
ENV GID=${GID:-1000}

WORKDIR /var/www/html

# Создаем пользователя и группу
RUN groupadd -g ${GID} laravel \
    && useradd -m -u ${UID} -g laravel -s /bin/bash laravel

# Настраиваем PHP-FPM под этого пользователя
RUN sed -i "s/^user = .*/user = laravel/" /usr/local/etc/php-fpm.d/www.conf \
    && sed -i "s/^group = .*/group = laravel/" /usr/local/etc/php-fpm.d/www.conf

# Установка зависимостей и расширений PHP
RUN apt-get update && apt-get install -y \
    git \
    bash \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    pkg-config \
    libssl-dev \
    zlib1g-dev \
    libcurl4-openssl-dev \
    libicu-dev \
    g++ \
    make \
    autoconf \
    && docker-php-ext-install pdo pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

EXPOSE 9000
CMD ["php-fpm"]
