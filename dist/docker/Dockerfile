FROM ${DEPENDENCY_PROXY_IMAGE_PREFIX}composer:2 AS composer

FROM php:cli

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update && \
    apt-get install -y unzip

WORKDIR /app
