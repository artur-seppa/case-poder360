FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libsqlite3-dev \
        nodejs \
        npm \
    && docker-php-ext-install pdo pdo_sqlite pcntl posix \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

RUN php artisan package:discover --ansi \
    && npm run build

RUN chmod +x docker-entrypoint.sh

EXPOSE 8000

CMD ["./docker-entrypoint.sh"]
