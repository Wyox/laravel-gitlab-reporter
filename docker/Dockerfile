ARG PHP_VERSION=8.2

FROM php:${PHP_VERSION}-cli-alpine

RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

ARG LARAVEL_VERSION=11
ARG TESTBENCH_VERSION=9.*
ARG PHPUNIT_VERSION=11.*

COPY composer.json ./

RUN composer config --no-plugins allow-plugins.php-http/discovery true && \
    composer require "illuminate/support:^${LARAVEL_VERSION}" \
        "orchestra/testbench:${TESTBENCH_VERSION}" \
        "phpunit/phpunit:${PHPUNIT_VERSION}" \
        --no-interaction --no-update && \
    composer update --prefer-dist --no-interaction --no-progress

COPY . .

CMD ["vendor/bin/phpunit", "--colors=always"]
