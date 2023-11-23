---
title: Composer-based web updater
date: 2022-11-09
area: core
tags: [composer, plugin, web-updater]
---

## Context

Currently, we are using a Slim Framework 2 based web updater which downloads the zip file from our server and unpacks it.
This is a very old approach and blocks us implementing new features like using Composer to install additional composer packages.

So our main problems are:

### Outdated stack of the updater itself

The Slim Framework 2 is outdated and is not supported anymore. We also don't have any good knowledge about the framework itself.

### Dangerous update process

- We assume that the shop files are in the same state as after the installation or the latest update. So we only apply the changeset to the latest update
- If the user runs Composer commands, the generated and dumped autoloader can differ and break the entire shop

### No Composer support

- Due to the simple unpacking of a changeset of the Shopware update, the user cannot use Composer to install additional packages
- Extensions have to package their own dependencies inside their extension zip and overwrite dependencies of Shopware or other extensions which can cause problems

## Decision

We will build a new Symfony based web updater which is packaged as an single phar file.
The Phar file will be downloaded from our server for each update and runs the newest web updater for the process of the update.
This allows us to react faster on bugs and implement new features, without having to wait for a new Shopware release.

The new web updater will use the same update process as the CLI update using composer.

So the process will be:

- The Shopware Admin will do a basic update check that all extensions are compatible with the next Shopware version
- If the user clicks on the update button the web updater will be downloaded and executed
- If the project is still based on the old structure, it will migrate it to Symfony Flex.
- Enable the maintenance mode using `bin/console`
- Run `composer update` to update Shopware
- Run `bin/console` to update the database
- Disable the maintenance mode
- Delete the Phar and Redirect the user to the Shopware Admin

The new way of managing a Shopware project will also allow us to setup a new project with the new tool.
So in the same way, we can provide an installer for new projects by utilising the `create-project` command of Composer.

## Consequences

The System requirements will change. We need access to functions like `proc_open` and `proc_close` in PHP and a PHP-CLI binary.

For updating config files Symfony Flex requires the `git` binary to be installed on the server and a git repository to be initialised.
This is a requirement for reviewing all the changes that have been made to the config files.
To avoid this we will backup the `.env` and the `.htaccess` file and overwrite all config files from a fresh installation and restore the backup.

To merge the upcoming changes of the `.htaccess` file we will use our already existing [UpdateHtaccess](https://github.com/shopware/platform/blob/6.4.17.0/src/Core/Framework/Update/Services/UpdateHtaccess.php) which is using the Markers to update only our own changes.
For the `.env` files we will make use of `.env.local` to be able to update the normal `.env` file. 

The normal CLI update way will require a git repository to be initialised and uses the normal Symfony update flow.
