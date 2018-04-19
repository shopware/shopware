FROM php:__PHP_VERSION__-apache

RUN apt-get update -qq && apt-get install -y -qq \
        libicu-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libpng12-dev \
        libcurl4-openssl-dev \
        software-properties-common  \
        libcurl3 curl \
        git \
        zip \
        unzip \
        openjdk-7-jdk \
        inotify-tools

RUN apt-get update -qq && apt-get install -y -qq \
        build-essential \
        libxml2-dev libxslt1-dev zlib1g-dev \
        git \
        mysql-client \
        sshpass \
        nano \
        sudo \
        vim \
        graphviz \
        netcat-openbsd \
        nodejs npm nodejs-legacy

RUN docker-php-ext-install iconv mcrypt mbstring \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install gd \
    && docker-php-ext-install zip \
    && docker-php-ext-install curl \
    && docker-php-ext-install intl \
    && docker-php-ext-install pdo \
    && docker-php-ext-install pdo_mysql

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN npm install npm@latest -g

ADD server-apache2-vhosts.conf /etc/apache2/sites-enabled/000-default.conf
ADD server-apache2-run-as.conf /etc/apache2/conf-available
RUN ln -s /etc/apache2/conf-available/server-apache2-run-as.conf /etc/apache2/conf-enabled

ADD php-config.ini /usr/local/etc/php/conf.d/php-config.ini
ADD timezone-berlin.ini /usr/local/etc/php/conf.d/timezone.ini
ADD xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

RUN a2enmod rewrite

COPY createuser.sh /tmp/createuser.sh
RUN chmod +rwx /tmp/createuser.sh
RUN /tmp/createuser.sh

RUN echo "alias ll='ls -ahl'" >> /etc/bash.bashrc

WORKDIR /var/www/shopware

COPY wait.sh /tmp/wait.sh
RUN chmod +x /tmp/wait.sh

COPY id_rsa /home/app-shell/.ssh
COPY id_rsa.pub /home/app-shell/.ssh

COPY run-container.sh /run-container.sh
RUN chmod +x /run-container.sh

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/usr/local/bin/ --filename=composer
RUN php -r "unlink('composer-setup.php');"

RUN composer config -g github-oauth.github.com 49c44da8f02ee5bcb55bfceeffc4c51f7adb1bff

RUN git clone https://github.com/tideways/profiler.git /php-profiler
RUN git clone https://github.com/tideways/php-profiler-extension.git /php-profiler-extension
RUN cd /php-profiler-extension && phpize
RUN cd /php-profiler-extension && ./configure
RUN cd /php-profiler-extension && make
RUN cd /php-profiler-extension && sudo make install
ADD tideways.ini /usr/local/etc/php/conf.d/tideways.ini

RUN cp /php-profiler/Tideways.php `php -r 'echo ini_get("extension_dir");'`

CMD /run-container.sh
