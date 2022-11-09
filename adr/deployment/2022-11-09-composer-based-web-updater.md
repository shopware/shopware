# 2022-11-09 - Composer-based Web Updater

## Context

Currently we use a Slim Framework 2 based Web Updater which downloads the Zip from our Server and unpacks it.
This is a very old approach and blocks us implementing new features like using Composer to install additional composer packages.

So our main problems are:

### Outdated stack of  the Updater itself

The Slim Framework 2 is outdated and is not supported anymore. We don't have also any good knowledge about the framework itself.

### Dangerous update process

- We assume that the shop files are the same as the install / or last update. So we update only the changeset to the last update
- If the user run composer commands the generated dumped autoloader can differ and break the entire shop

### No Composer support

- Due to the simple unpacking of an changeset of the Shopware update the user cannot use Composer to install additional packages
- Extensions has to package their own dependencies inside their extension zip and overwrite dependencies of Shopware or other extensions which can make problems

## Decision

We will make a new Symfony based Web Updater which is packaged as an single Phar file.
The Phar file will be downloaded from our Server for each update and run the newest Web Updater for the process of the update.
This allows us to react faster on bugs and implement new features, without having to wait for a new Shopware release.

The new Web Updater will use the same update process as the CLI Update using composer.

So the process will be:

- The Shopware Admin will do a basic update check that all extensions are compatible with the next Shopware version
- If the user clicks on the update button the Web Updater will be downloaded and run
- If the project still bases on the old structure migrating it to Symfony Flex.
- Enable using `bin/console` the maintenance mode
- Run `composer update` to update the Shopware
- Run `bin/console` to update the database
- Disable the maintenance mode
- Delete the Phar and Redirect the user to the Shopware Admin

The new way of managing a Shopware project will allow us to setup a new project also with the new Tool.
So we can provide in the same way also an installer for new projects by utilizing the `create-project` of Composer.

## Consequences

The System requirements will change that we need access to functions like `proc_open` and `proc_close` in PHP and a PHP-CLI binary.

Symfony Flex requires for updating config files the `git` binary to be installed on the server and a git repository to be initialized.
This is a requirement to have the user reviewed all the changes that have been made to the config files.
To avoid this we will backup the `.env` and the `.htaccess` file and overwrite all config files from a fresh installation and restore the backup.

To merge the upcoming changes of the `.htaccess` file we will use our already existing [UpdateHtaccess](https://github.com/shopware/platform/blob/6.4.17.0/src/Core/Framework/Update/Services/UpdateHtaccess.php) which is using the Markers to update only our own changes.
For the `.env` files we would a custom solution to merge the changes.

The normal CLI update way will require an git repository to be initialized and use the normal Symfony update flow.
