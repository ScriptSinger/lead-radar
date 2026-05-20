FROM composer:2

ARG UID
ARG GID

ENV UID=${UID:-1000}
ENV GID=${GID:-1000}

WORKDIR /var/www/html

RUN addgroup -g ${GID} laravel
RUN adduser -G laravel -D -s /bin/sh -u ${UID} laravel
