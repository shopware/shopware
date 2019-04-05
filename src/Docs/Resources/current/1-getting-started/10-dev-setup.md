[titleEn]: <>(Developer Setup)
[titleDe]: <>(Developer Setup)
[wikiUrl]: <>(../getting-started/dev-setup?category=shopware-platform-en/getting-started)

# Development setup guide

This guide shows you how to setup a minimal development system.

The following development environment setups are shown:

- docker >= 17.06.0
- Ubuntu 18.04 LTS
- Ubuntu 18.04 LTS on Windows 10 with Windows Subsystem for Linux

Notice: The following guide is not tested with docker running natively on Windows! 
Please use a Linux subsystem instead. 

It is also not tested with macOS. If you want to use macOS,
it's recommended to set up a local webserver (e.g. apache) with mod_rewrite, a mysql server and
PHP. There are good tutorials out there on how to set it up. After you have set up the local stack,
just create a virtual host similar to this one: [vhost](#web-root). 
You can use port 80 instead of 8080.

It should be easy to setup different environments.


## Repository structure

This guide uses the [development] template from github. The [core] system, that contains most of the code, is pulled
into `platform` as a composer dependency.

## Requirements

PHP
-  7.2 or greater
- `memory_limit` 512M or greater
- `max_execution_time` 30 seconds or greater
- Extensions: curl, dom, fileinfo, gd, iconv, intl, json, mbstring, pdo, pdo_mysql, phar, simplexml, tokenizer, xml,
xmlwriter, zip
- Composer 1.6 or greater

SQL
- MySQL 5.7.21 or greater
- MariaDB 10.3 or greater
    
JavaScript
- Node.js 8.10.0 or greater
- NPM 6.5.0 or greater

Various
- Webserver with url rewrite (Apache 2.4 or nginx)
- Bash

## Recommendations

- Zend Opcache (256M or greater)
- APCu (128M or greater)
- Webserver with HTTP2 support

Adminier (https://www.adminer.org/) is our recommended database administration tool since it has better
support for binary data types.

## Docker setup

The easiest way to get started is to use docker. You only need 
- php (packages `php` and `php-cli` )
- `docker` and `docker-compose`
- bash

to be installed.

Your current user should also be a member of the "docker" group, otherwise you have to execute all
docker commands with root-permissions.

```bash
git clone http://github.com/shopware/development.git shopware-dev
cd shopware-dev
git clone http://github.com/shopware/platform.git platform

# start the docker containers
./psh.phar docker:start

# fix permissions on $HOME/.composer and $HOME/.npm
sudo chown $USER:$USER $HOME/.composer $HOME/.npm

Notice: If you use macOS, use the following command:
sudo chown "$USER":staff $HOME/.composer $HOME/.npm


#append "shopware.test" to your etc/hosts file
echo "127.0.0.1 shopware.test" | sudo tee -a /etc/hosts

# ssh into the app container
./psh.phar docker:ssh
```

Follow the steps described in [common setup steps](#Common-setup-steps) and return here once you are done.

Afterwards the system should  be fully functional. You should be able to access the example storefront <http://shopware.test:8000>
and the admin <http://shopware.test:8000/admin>. The default credentials are `admin` and `shopware`.

To shutdown the container, logout from the container and run stop:

```bash
./psh.phar docker:stop
```

Make sure that you run all commands except the docker commands (`docker:*`) inside the container.


### Directory mappings

Docker maps some directories on your system into the container. That means that some state may persist after stopping
the container.

- `.:/app`: the container maps the project root directory into `/app`
- `~/.composer:/.composer`: maps the users composer directory into the container
- `~/.npm:/home/application/.npm`: maps the users npm directory into the container

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
GRANT ALL PRIVILEGES ON shopware_e2e.* TO 'app'@'localhost';
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

Clone the development repository from github
```bash
git clone https://github.com/shopware/development.git $HOME/shopware-dev
cd $HOME/shopware-dev
git clone https://github.com/shopware/platform.git platform
```

Follow the steps described in [common setup steps](#Common-setup-steps) and return here once you are done.

### Web root

This section shows how to setup a web root with apache that links to a project directory in the users home. This is an
example setup. You are free to use a different setup. The only hard requirements are:

* url rewrite root to `index.php`

Add your user to the `www-data` group and allow the group to access the project files and setgid on all directories:
```bash
sudo usermod -a -G www-data $USER
sudo chgrp -R www-data $HOME/shopware-dev
sudo find $HOME/shopware-dev -type d -exec chmod g+s '{}' \;
sudo chmod g+w -R $HOME/shopware-dev/{var,public/{media,thumbnail}}
```
Add host:
```bash
echo "127.0.0.1 shopware.test" | sudo tee --append /etc/hosts
```
Link www root to project dir:
```bash
sudo ln -s $HOME/shopware-dev /var/www/shopware.test
```
Add virtual host `/etc/apache2/sites-available/shopware.test.conf`:

```apacheconfig
LISTEN 8000

<VirtualHost *:8000>
    DocumentRoot "/var/www/shopware.test/public"
    ServerName shopware.test
    <Directory "/var/www/shopware.test/public">
        AllowOverride All
    </Directory>
</VirtualHost>
```

Run the following commands to enable the vhost:
```bash
# enable shopware vhost
sudo a2ensite shopware.test.conf

# disable default vhost
sudo a2dissite 000-default.conf

# enable mod_rewrite
sudo a2enmod rewrite

# restart apache
sudo systemctl restart apache2
```

The system should now be fully functional. You should be able to access the example storefront <http://shopware.test:8000> and
the admin <http://shopware.test:8000/admin>.


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


## Common setup steps

Run the setup script (either from your local installation dir or inside the docker)
```bash
bin/setup
```
This will start the shopware setup process which asks for some basic information. You can skip all inputs by pressing 
enter on each question. In this case the default value, specified in brackets [] behind the question, is chosen.

1. `Application environment`: whether a development or production instance should be prepared. 
Choose ```dev```.
2. `URL to your /public folder`: the URL from which the shop should be reached. Use `http://shopware.test:8000`.
3. `Database host`: where the database is hosted.
    - **Local Installation** Use the default.
    - **Docker Installation** Use `app_mysql`.
4. `Database port`: the port under which the database may be reached. Use the default.
5. `Database name`: the name of the database which Shopware will use. Use the default.
6. `Database user`: the user shopware will use to access the database. Use the default.
7. `Database password`: password of the given database user. Use `app`.

Shopware and all of the its dependecies will now be installed. This may take a while.
In the file `.psh.yml.override` any of the information entered during the setup process can also be reviewed or changed.

**Only local installations:** Append `CHROME_BIN: "chromium-browser"` to the `.psh.yml.override` const section.

Shopware needs the `COMPOSER_HOME` environment variable to be set.
Per default it will be set to the global `COMPOOSER_HOME` value. If this is empty, `/home/<user>/.composer` will be used.
To override this behaviour, use the `.psh.yaml.override` and add the following:

```yaml
...
dynamic:
  COMPOSER_HOME: echo "./foo"
```
This will set the `COMPOSER_HOME` to `./foo`

Finally init/build the administration app.
```bash
./psh.phar administration:init
./psh.phar administration:build
```

Afterwards continue with your system-specific set-up.

## Testing your setup

You can run the following commands to test your environment. All tests should complete without errors,
but some test may sometimes be marked as "S" (skipped). This is not considered an error, as those
tests test a feature that is not fully implemented yet. As the latest git master (which
you are using when following this guide) represents the bleeding edge of development, you'll see these
quite often.

**Warning:** The following operations will reset your database.
```bash
# reset database
./psh.phar init

# run unit tests
./psh.phar unit

# run the nightwatch end to end (e2e) tests
./psh.phar administration:init
./psh.phar administration:build
./psh.phar administration:e2e

# run karma unit tests
./psh.phar administration:unit
```

## Updating Shopware 

The Shopware repository is split into two subrepositories: The platform repository, which contains the 
code for the core component, and the development repository, which contains the development template. In order to keep your
dev setup in synch with the newest codebase, you thus have to pull **both** repositories.

To pull the main repository use 
```bash
cd $HOME/shopware-dev
git pull origin master
```

To pull the Platform repository use
```bash
cd $HOME/shopware-dev/platform/
git pull origin master
```
After pulling the latest changes you should clear the cache via
```bash
sudo rm -rf $HOME/shopware-dev/var/
```

## Common tasks 

###psh
As you may have noticed, most of the commands concerning shopware are executed using *psh*.
psh is a task runner with many handy features. For detailed information see <https://github.com/shopwareLabs/psh>

#### Config

There are two configuration files `.psh.yml.dist` and `.psh.yml.override`. `.psh.yml.dist` is distributed with the code
and contains defaults. The `.psh.yml.override` config is generated by the setup routine and contains local configuration
like the database connection settings.

### List of Commands

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
| administration:watch | start a local server for the administration, including hot reloading and live linting | uses port 8080 |
| administration:e2e   | run the nightwatch e2e tests | you need to reset your database, initialize and build the administration before |

##Tips

### Customization

You can customize your setup by
- adding custom actions to the `.psh.yml.override` file.
- using the git ignored `custom/` directory to add custom and unversioned configuration.


### Configure PHPStorm

- Add `platform` as your source directory
  - settings > directories > select directory > Mark as: Sources
- Add `platform` directory to your version control
  - settings > Version Control > "+" > select directory
- To improve the performance of PHPStorm, you should exclude the `/var` directory from indexing
  - settings > directories > select `/var` > Mark as: Excluded


#### xdebug docker

<https://gist.github.com/jehaby/61a89b15571b4bceee2417106e80240d>


## Troubleshooting

- if you run into composer memory limit errors: 
<https://getcomposer.org/doc/articles/troubleshooting.md#memory-limit-errors>

- if you get the following error when calling the storefront with a local setup (not docker): 
  `Argument 1 passed to Shopware\\Storefront\\Content\\Controller\\Widget\\IndexController::shopMenuAction() must be an instance of Shopware\\Core\\System\\SalesChannel\\CheckoutContext, null given`
  it usually means that you haven't configured the vhost properly or the configured host doesn't
  match with the host you are using to call the website

[development]: https://github.com/shopware/development
[core]: https://github.com/shopware/platform
