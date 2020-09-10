FROM php:fpm
WORKDIR "/application"

RUN apt-get update \
    && apt-get -y --no-install-recommends install \
        git \
        git-flow \
        wget \
        zip \
        unzip \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

RUN docker-php-ext-configure pdo_mysql  \
    && docker-php-ext-install -j$(nproc) pdo_mysql

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY php-ini-overrides.ini /etc/php/7.4/fpm/conf.d/99-overrides.ini

RUN wget https://get.symfony.com/cli/installer -O - | bash \
    && mv /root/.symfony/bin/symfony /usr/local/bin/symfony

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php \
    && rm composer-setup.php \
    && mv composer.phar /usr/local/bin/composer

