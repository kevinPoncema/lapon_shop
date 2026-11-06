FROM php:8.5-cli

LABEL maintainer="Shop Team"

ARG WWWUSER=1000
ARG WWWGROUP=1000

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    gnupg \
    gosu \
    curl \
    ca-certificates \
    zip \
    unzip \
    git \
    procps \
    libzip-dev \
    libpq-dev \
    netcat-openbsd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install docker-php-extension-installer and PHP extensions
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    zip \
    bcmath \
    intl \
    pcntl \
    gd \
    exif \
    sockets \
    opcache

# Install Node.js 22 LTS
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g npm@latest \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure custom php.ini
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Set up user and permissions
RUN groupadd --force -g $WWWGROUP laravel \
    && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u $WWWUSER laravel \
    && mkdir -p /var/www/html /home/laravel/.composer /home/laravel/.npm \
    && chown -R laravel:laravel /home/laravel

WORKDIR /var/www/html

# Copy entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000 5173 5174

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php", "artisan", "dev", "--inline"]
