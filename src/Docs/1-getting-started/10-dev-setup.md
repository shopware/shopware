[titleEn]: <>(Developer Setup)
[wikiUrl]: <>(../getting-started/dev-setup?category=shopware-platform-en/getting-started)

# Development setup guide

This guide shows you how to setup a minimal development system.

The following development environment setups are shown:

- docker >= 17.06.0
- Ubuntu 18.04 LTS
- Ubuntu 18.04 LTS on Windows 10 with Windows Subsystem for Linux

It should be easy to setup different environments.


## Repository structure

This guide uses the [development] template from github. The [core] system, that contains most of the code, is pulled
into `vendor/shopware/platform` as a composer dependency.

## Hard requirements

The following software is always required:

- php >= 7.1
- php extensions: dom, fileinfo, gd, iconv, intl, json, mbstring, pdo, pdo_mysql, phar, simplexml, tokenizer, xml,
xmlwriter, zip
- mysql >= 5.7 or mariadb >= 10.3
- composer >= 1.6
- nodejs >= 8.10.0
- npm >= 3.5.2
- bash
- a webserver with url rewrite

## Docker setup

The easiest way to getting started is to use docker. You only need to have a working docker, docker-compose and bash
installation.

```bash
git clone http://github.com/shopware/development.git shopware-dev
cd shopware-dev

# start the docker containers
./psh.phar docker:start

# fix permissions on $HOME/.composer
sudo chown $USER:$USER $HOME/.composer

# ssh into the app container
./psh.phar docker:ssh

# inside the container
./psh.phar init
```

The system should now be fully functional. You should be able to access the example storefront <http://shopware.dev>
and the admin <http://shopware.dev/admin>.

To shutdown the container, logout from the container and run stop:

```bash
./psh.phar docker:stop
```

Make sure that you run all commands except the docker commands (`docker:*`) inside the container.


### Directory mappings

Docker maps some directories on your system into the container. That means that some state may persist after stopping
the container.

- `.:/app`: the container maps the project root directory into `/app`
- `~/.composer/cache:/.composer/cache`: maps the users composer cache into the container
- `~/.npm:/home/app/.npm`: maps the users npm directory into the container

When changing branches you should always clear your cache.

### Config

You can override the docker-compose config by changing the template used in the `.psh.yml.override` config.


## Ubuntu 18.04 LTS

This section shows you how to setup a development environment on Ubuntu 18.04 LTS.

### Base system

Install mysql and apache
```bash
sudo apt install php libapache2-mod-php php-cli
sudo apt install mysql-server
```

Open a mysql shell `sudo mysql ` and add a new user:
```mysql
CREATE USER 'app'@'localhost' IDENTIFIED BY 'app';
GRANT ALL PRIVILEGES ON shopware.* TO 'app'@'localhost';
GRANT ALL PRIVILEGES ON shopware_test.* TO 'app'@'localhost';
FLUSH PRIVILEGES;
QUIT
```

Install build tools
```bash
sudo apt install composer nodejs npm
```

Install dependencies for e2e and karma tests
```bash
sudo apt install chromium-browser
sudo apt install default-jre-headless
```

### PHP modules

Install the php modules:
```bash
sudo apt install php-gd php-intl php-iconv php-mbstring php-mysql php-xml php-zip php-json
```

### Shopware setup

Clone the development repository from github
```bash
git clone https://github.com/shopware/development.git $HOME/shopware-dev
cd $HOME/shopware-dev
```

Run the setup script
```bash
bin/setup
```
This will start the shopware setup process which asks for some basic information. You can skip all inputs by pressing 
enter on each question. In this case the default value, specified in brackets [] behind the question, is chosen.

1. `Application environment`: whether a development or production instance should be prepared. 
Choose ```dev```.
2. `URL to your /public folder`: the URL from which the shop should be reached. Use `shopware.dev`.
3. `Tenant id`: the tenant ID for this shop. Use the default.    
4. `Database host`: where the database is hosted. Use the default.
5. `Database port`: the port under which the database may be reached. Use the default.
6. `Database name`: the name of the database which Shopware will use. Use the default.
7. `Database user`: the user shopware will use to access the database. Use the default.
7. `Database password`: password of the given database user. Use `app`.

Shopware and all of the its dependecies will now be installed. This may take a while.

Append `CHROME_BIN: "chromium-browser"` to `.psh.yml.override` const section.
In this file any of the information entered during the setup process can also be reviewed or changed.

Finally init/build the administration app.
```bash
./psh.phar administration:init
./psh.phar administration:build
```

### Web root

This section shows how to setup a web root with apache that links to a project directory in the users home. This is an
example setup. You are free to use a different setup. The only hard requirements are:

* url rewrite root to `index.php`
* `TENANT_ID` environment variable must be set with valid uuid4 and must be the same value as in `.psh.yml.override`

Add your user to the `www-data` group and allow the group to access the project files and setgid on all directories:
```bash
sudo usermod -a -G www-data $USER
sudo chgrp -R www-data $HOME/shopware-dev
sudo find $HOME/shopware-dev -type d -exec chmod g+s '{}' \;
sudo chmod g+w -R $HOME/shopware-dev/{var,public/{media,thumbnail}}
```
Add host:
```bash
echo "127.0.0.1 shopware.dev" | sudo tee --append /etc/hosts
```
Link www root to project dir:
```bash
sudo ln -s $HOME/shopware-dev /var/www/shopware.dev
```
Add virtual host `/etc/apache2/sites-available/shopware.dev.conf`:

```apacheconfig
<VirtualHost *:80>
    DocumentRoot "/var/www/shopware.dev/public"
    ServerName shopware.dev
    SetEnv TENANT_ID 20080911ffff4fffafffffff19830531
    <Directory "/var/www/shopware.dev/public">
        AllowOverride All
    </Directory>
</VirtualHost>
```

Run the following commands to enable the vhost:
```bash
# enable shopware vhost
sudo a2ensite shopware.dev.conf

# disable default vhost
sudo a2dissite 000-default.conf

# enable mod_rewrite
sudo a2enmod rewrite

# restart apache
sudo systemctl restart apache2
```

The system should now be fully functional. You should be able to access the example storefront <http://shopware.dev> and
the admin <http://shopware.dev/admin>.


## Testing your setup

You can run the following commands to test your environment. All tests should complete without errors.
```bash
# reset database
./psh.phar init

# run unit tests
./psh.phar unit

# run e2e tests
./psh.phar administration:init
./psh.phar administration:build
./psh.phar administration:e2e

# run karma unit tests
./psh.phar administration:unit
```

## Executing common tasks with psh

*psh* is a task runner with many handy features. For detailed information see <https://github.com/shopwareLabs/psh>

### psh config

There are two configuration files `.psh.yml.dist` and `.psh.yml.override`. `.psh.yml.dist` is distributed with the code
and contains defaults. The `.psh.yml.override` config is generated by the setup routine and contains local configuration
like the database connection settings.


### psh action overview

Run `./psh.phar` to get an overview of all tasks. You can run `./psh.phar administration:` to get a list of all 
administration tasks.

*Useful tasks:*

| Action | Description | Notes |
| ------ | ----------- | ----- |
| init                 | initialize the system and database | resets the database if it already exists |
| demo-data            | create demo data |
| cache | clear the cache | |
| administration:init  | install all dependencies of the administration | |
| administration:build | build the administration | |
| administration:watch | start a local server for the administration, including hot reloading and live linting | |
| administration:e2e   | run the nightwatch e2e tests | see [E2E Tests](#e2e-tests) |

### E2E tests

To run the nightwatch end to end tests you need to reset your database, initialize and build the administration before
running the tests.
```bash
./psh.phar init
./psh.phar administration:init
./psh.phar administration:build
./psh.phar administration:e2e
```

## Customization

You can customize your setup by
- adding custom actions to the `.psh.yml.override` file.
- using the git ignored `custom/` directory to add custom and unversioned configuration.


## Configure PHPStorm

- Add `vendor/shopware/platform` as your source directory
  - settings > directories > select directory > Mark as: Sources
- Add `vendor/shopware/platform` directory to your version control
  - settings > Version Control > "+" > select directory

### xdebug docker

<https://gist.github.com/jehaby/61a89b15571b4bceee2417106e80240d>


## Windows (Ubuntu 18.04 LTS)

To setup Ubuntu

- install Ubuntu 18.04 LTS from the Windows Store
- and start it once to setup the environment

After that you can start bash with: `%windir%\system32\bash.exe` and run the following commands to update the system:

```bash
sudo apt update
sudo apt upgrade
```

You can now follow the [Ubuntu 18.04 LTS guide](#ubuntu-18.04-lts). There are only a few differences:

- there is no systemd - use `service <service> start/stop/restart` instead
- services are not started automatically after install. You need to start the services once:
```bash
service apache2 start
service mysql start
```


### Tips
- deactivate your AV software live file scanning to improve the performance


## Troubleshooting

- if you run into composer memory limit errors: 
<https://getcomposer.org/doc/articles/troubleshooting.md#memory-limit-errors>


[development]: https://github.com/shopware/development
[core]: https://github.com/shopware/platform
