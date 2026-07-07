FROM php:8.2-apache

# 1. Instalar dependencias del sistema y extensiones PHP para bases de datos
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-install gd zip pdo pdo_mysql mysqli

# 2. Habilitar el módulo de reescritura de Apache
RUN a2enmod rewrite

# 3. Traer Composer integrado
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Definir carpeta de trabajo y copiar el código
WORKDIR /var/www/html
COPY . /var/www/html/

# 5. Instalar dependencias si existen (si no, continúa)
RUN composer install --no-dev --optimize-autoloader --no-interaction || true

# 6. Dar permisos correctos a Apache
RUN chown -R www-data:www-data /var/www/html