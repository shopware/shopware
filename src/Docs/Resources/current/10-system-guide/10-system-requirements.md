[titleEn]: <>(System Requirements)
[hash]: <>(article:system_requirements)

Before installing Shopware 6, you should take a quick look at the requirements to check if your local environment is capable of running it.

You can use these commands for checking your actual environment:
- `php -v`: Show CLI PHP version
- `php -m`: Show CLI PHP modules
- `php -i | grep memory_limit`: Show your actual CLI PHP memory limit
- `composer -v`: Show your actual composer version
- `node -v`: Show you actual Node version
- `npm -v`: Show you actual NPM version

To get more information about your server PHP setup, you can create a `phpinfo.php` file with this content:
```php
<?php phpinfo(); ?>
```
When you now open your Browser and go to the `phpinfo.php` page then you can see all information about
your actual PHP setup. Check if they also matches with the requirements.

## System Requirements

### Operating System

Although Shopware 6 support most UNIX like environments, we recommend using **Ubuntu 18.04 LTS** or  **macOS Mojave 10.14** to get the best experience.

### Environment

PHP
*  7.4 or higher
* `memory_limit` 512M minimum
* `max_execution_time` 30 seconds minimum
* Extensions:
    * ext-curl
    * ext-dom  
    * ext-fileinfo  
    * ext-gd  
    * ext-iconv  
    * ext-intl  
    * ext-json  
    * ext-libxml  
    * ext-mbstring  
    * ext-openssl  
    * ext-pcre  
    * ext-pdo  
    * ext-pdo_mysql  
    * ext-phar  
    * ext-simplexml  
    * ext-xml  
    * ext-zip  
    * ext-zlib
* Composer 2.0 or higher

SQL
* MySQL 5.7.21 or higher
* MariaDB 10.3.22 or higher

JavaScript
* Node.js 12.21.0 or higher
* NPM 6.5.0 or higher

Various
* Apache 2.4 or higher with mod-rewrite enabled
* Bash
* Git

### Recommendations

- Zend Opcache (256M or more)
- APCu (128M or more)
- Webserver with HTTP2 support

Adminer (https://www.adminer.org/) is our recommended database administration tool since it has better support for binary data types.

## Docker

If you are working on Linux there is a curated docker setup, that takes care of setting up the environment for you.

In this case you need:

* PHP 7.4+ CLI
* docker
* docker-compose
* bash
