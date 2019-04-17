[titleEn]: <>(Installation guide)

Before digging deeper into the Shopware Platform we recommend creating a local installation. You should have chosen if you want to install it on your local host or with docker by now and have your system already set up to fulfill the [requirements](./../10-requirements/__categoryInfo.md). 

## Preparation

Either installation method requires you to check out the sources first. The Shopware Platform is split into two repositories the [development template](https://github.com/shopware/development) and the [platform](https://github.com/shopware/platform) itself.

Let's start by cloning the development template:

```bash
> git clone git@github.com:shopware/development.git
```

You now have the application template for the Shopware Platform in the directory `development`, we now change into it:

```bash
> cd development
```

and clone the platform repository into its default directory `platform`. *Note This is important for autoloading purposes.*

```bash
> git clone git@github.com:shopware/platform.git

```

## Docker installation (recommended)

The docker installation is the easiest way to get a running Shopware Platform. This way you can setup the Shopware Platform with just three easy commands: 

Build and start the containers:

```bash
> ./psh.phar docker:start

```

Access the application container:

```bash
> ./psh.phar docker:ssh
```

Execute the installer:

```bash
> ./psh.phar install 
```

This may take a while since many caches need to be generated on first execution, but only on first execution.

To be sure that the installation succeeded, just open the following url in your favorite browser: [http://localhost:8000/](http://localhost:8000/)

## Local installation
If you are working on a Mac or it's otherwise impossible to get docker up and running on your development environment you can install the Shopware Platform locally. **But be aware that this will be the by far more complex solution since additional or changed system requirements need to be managed by you.**

Once you setup all the required packages mentioned in [requirements](./../10-requirements/__categoryInfo.md) there are two main goals you need to accomplish:

### Setting up your webserver

First up we need to setup Apache to locate the Shopware Platform. You should add a vhost to your Apache site configuration that looks like this:

```xml
<VirtualHost *:80>
   ServerName "HOST_NAME"
   DocumentRoot _DEVELOPMENT_DIR_/public

   <Directory _DEVELOPMENT_DIR_>
      Options Indexes FollowSymLinks MultiViews
      AllowOverride All
      Order allow,deny
      allow from all
   </Directory>

   ErrorLog ${APACHE_LOG_DIR}/shopware-platform.error.log
   CustomLog ${APACHE_LOG_DIR}/shopware-platform.access.log combined
   LogLevel debug
</VirtualHost>
```

Please remember to replace `_DEVELOPMENT_DIR_` and `_HOST_NAME_` with your preferences respectively and add the corresponding entry to your `/etc/hosts` file.

After a quick restart of apache you are done here.

### Setting up Shopware

A simple cli installation wizard can be invoked by executing:

```bash
> bin/setup
```

> Note: If something goes wrong during installation check if `.psh.yaml.override` exists. If not restart setup, if yes execute `./psh.phar install` to restart the setup process

Voila, the Shopware Platform is installed. To be sure that the installation succeeded, just open the configured host url in your favorite browser.

## Specific guides

* [MacOSX using MAMP](./../25-system-installation-guides/10-mac-os-x.md)

## Updating the repositories

It is important to keep the `platform` and the `development` repository in sync. **We highly discourage to update either without the other!**

The following steps should always yield a positive result:

```bash
> git pull
> cd platform
> git pull
> cd ..
> composer update
> rm -R var/cache/*
> ./psh.phar install
```

Please note that this will reset your database.
