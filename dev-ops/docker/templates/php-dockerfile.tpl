FROM php:__PHP_VERSION__-apache

WORKDIR /var/www/shopware

RUN apt-get update -qq && apt-get install -y -qq \
        libicu-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libpng12-dev \
        libcurl4-openssl-dev \
        software-properties-common  \
        libcurl3 \
        curl \
        wget \
        git \
        zip \
        unzip \
        inotify-tools \
        build-essential \
        libxml2-dev \
        libxslt1-dev \
        zlib1g-dev \
        git \
        mysql-client \
        sshpass \
        nano \
        sudo \
        vim \
        graphviz \
        netcat-openbsd

RUN curl -sL https://deb.nodesource.com/setup_8.x | bash
RUN apt-get install nodejs -y

RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install iconv \
        mcrypt \
        mbstring \
        gd \
        zip \
        curl \
        intl \
        opcache \
        pdo \
        pdo_mysql

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# apache setup
ADD server-apache2-vhosts.conf /etc/apache2/sites-enabled/000-default.conf
ADD server-apache2-run-as.conf /etc/apache2/conf-available
RUN ln -s /etc/apache2/conf-available/server-apache2-run-as.conf /etc/apache2/conf-enabled
RUN a2enmod rewrite

# php setup
ADD php-config.ini /usr/local/etc/php/conf.d/php-config.ini
ADD timezone-berlin.ini /usr/local/etc/php/conf.d/timezone.ini
ADD xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# macOS fix
RUN groupdel dialout

# setup app user
COPY createuser.sh /tmp/createuser.sh
RUN chmod +rwx /tmp/createuser.sh
RUN /tmp/createuser.sh
COPY id_rsa /home/app-shell/.ssh
COPY id_rsa.pub /home/app-shell/.ssh

# copy setup scripts
COPY wait.sh /tmp/wait.sh
RUN chmod +x /tmp/wait.sh

# setup composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin/ --filename=composer \
    && php -r "unlink('composer-setup.php');"

RUN composer config -g github-oauth.github.com 49c44da8f02ee5bcb55bfceeffc4c51f7adb1bff

# setup tideways
RUN git clone https://github.com/tideways/profiler.git /php-profiler \
    && git clone https://github.com/tideways/php-profiler-extension.git /php-profiler-extension

RUN cd /php-profiler-extension \
    && phpize \
    && ./configure \
    && make \
    && sudo make install \
    && cp /php-profiler/Tideways.php `php -r 'echo ini_get("extension_dir");'`

ADD tideways.ini /usr/local/etc/php/conf.d/tideways.ini

# Install Google Chrome
RUN wget -q -O - https://dl-ssl.google.com/linux/linux_signing_key.pub | apt-key add -
RUN sh -c 'echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" >> /etc/apt/sources.list.d/google.list'
RUN apt-get update && apt-get install -y google-chrome-stable

COPY run-container.sh /run-container.sh
RUN chmod +x /run-container.sh

CMD /run-container.sh
