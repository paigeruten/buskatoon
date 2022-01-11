FROM php:7.4-cli

RUN apt-get update
RUN apt-get install -y git unzip

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN useradd php
RUN mkdir -p /home/php
RUN chown php:php /home/php
USER php

WORKDIR /srv/www/buskatoon.ca

COPY composer.json composer.lock .
RUN composer install

COPY update_opendata.sh import_csv.php .
RUN sh update_opendata.sh

COPY buskatoon.php run.sh .

RUN touch vehicle_positions.json

CMD ["./run.sh"]

