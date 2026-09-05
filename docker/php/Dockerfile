FROM php:8.3-fpm

# Dependências do sistema
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    libzip-dev zip unzip libpq-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer 2
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Usuário não-root
RUN groupadd -g 1000 www && useradd -u 1000 -ms /bin/bash -g www www

WORKDIR /var/www

USER www

EXPOSE 9000
CMD ["php-fpm"]
