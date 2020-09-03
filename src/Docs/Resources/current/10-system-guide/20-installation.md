[titleEn]: <>(Installation)
[hash]: <>(article:system_installation)

Before digging deeper into Shopware 6 we recommend creating a local installation.
You should have chosen if you want to install it on your local host or with docker by now and 
have your system already set up to fulfill the [requirements](./10-system-requirements.md). 

## Preparation

Either installation method requires you to check out the sources first.
Shopware 6 is split into two repositories the [development template](https://github.com/shopware/development) and the [platform](https://github.com/shopware/platform) itself.

Let's start by cloning the development template:

```bash
> git clone https://github.com/shopware/development.git
```

You now have the application template for Shopware 6 in the directory `development`, we now change into it:

```bash
> cd development
```

Per default the development template has `shopware/platform` in the version `dev-master` as requirement in its `composer.json` file.
This always corresponds to the latest commit on the master branch on GitHub.
If you want to use a specific version change `dev-master` to `6.1` for example.
Now Shopware 6.1 will be installed instead of the latest master state.

Only if you want to work with the Shopware platform code itself, e.g. in order to create a pull request for it, you should clone the `platform` code manually.
Before doing so, remove the existing platform directory.

```bash
> rm -rf platform
> git clone https://github.com/shopware/platform.git
```

Otherwise, the Shopware platform code would be placed into a `vendor/shopware/platform` directory, where you don't want to change any code.
There's a good reason, why many IDEs try to prevent you from changing code in the `vendor` directory.

## Docker installation (recommended)

<p class="alert is--error">
    Docker is <b>not</b> the recommended way to install Shopware 6 on a Mac, due to performance issues.
    Instead take a closer look at our
    <a href="https://docs.shopware.com/en/shopware-platform-dev-en/system-guide/system-installation-guides/vagrant">vagrant guide</a>
    or the <a href="https://docs.shopware.com/en/shopware-platform-dev-en/system-guide/system-installation-guides/mac-os-x">MacOS using MAMP installation guide</a>
    or install <a href="https://docs.shopware.com/en/shopware-platform-dev-en/system-guide/system-installation-guides/docker-sync">docker-sync</a>
     or <a href="https://docs.shopware.com/en/shopware-platform-dev-en/system-guide/system-installation-guides/dockware">dockware</a> to use native mounting with Docker volumes. 
</p>

The docker installation is the easiest way to get a running Shopware 6. This way you can setup Shopware 6 with just three easy commands: 

1. Build and start the containers:

    ```bash
    > ./psh.phar docker:start
    ```

2. Access the application container:

    ```bash
    > ./psh.phar docker:ssh
    ```

3. Execute the installer inside the docker container:

    ```bash
    > ./psh.phar install 
    ```

This may take a while since many caches need to be generated on first execution, but only on first execution.

To be sure that the installation succeeded, just open the following url in your favorite browser: [http://localhost:8000/](http://localhost:8000/)

After exploring Shopware 6 you can terminate it with these two commands:

1. Leave the shell:

    ```bash
    > exit
    ```

2. Stop the containers:

    ```bash
    > ./psh.phar docker:stop
    ```

## Local installation

If it's impossible to get docker up and running on your development environment you can install Shopware 6 locally.
**But be aware that this will be the by far more complex solution since additional or changed system requirements need to be managed by you.**

Once you setup all the required packages mentioned in [requirements](./10-system-requirements.md) there are two main goals you need to accomplish:

### Setting up your webserver

First up we need to setup Apache to locate Shopware 6.
You should add a vhost to your Apache site configuration that looks like this:

```apacheconfig
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

> Note: If something goes wrong during installation check if `.psh.yaml.override` exists.
> If not restart setup, if yes execute `./psh.phar install` to restart the setup process

Voila, Shopware 6 is installed.
To be sure that the installation succeeded, just open the configured host url in your favorite browser.

## Specific guides

* [MacOSX using MAMP](./30-system-installation-guides/10-mac-os-x.md)
* [Vagrant VM](./30-system-installation-guides/20-vagrant.md)
* [Using docker-sync](./30-system-installation-guides/30-docker-sync.md)

## Updating the repositories

It is important to keep the `platform` and the `development` repository in sync.
**We highly discourage to update either without the other!**

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
