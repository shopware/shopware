---
title: New option for plugins using Composer 2
issue: NEXT-1797
flag: FEATURE_NEXT_1797
author: Michael Telgmann
author_github: @mitelg
---
# Core
* Changed dependency `composer/composer` to version 2
* Added new dependency `composer-runtime-api` with version 2.0
* Added new method `executeComposerCommands` to `Shopware\Core\Framework\Plugin` to enable composer commands during plugin install/update/uninstall
___
# Upgrade Information

## Update to Composer 2
Make sure that your `composer.json` file in your plugin matches the requirements of [Composer](https://getcomposer.org/doc/04-schema.md).
Especially the `name` property should be checked.

## Composer runtime API
With Shopware 6.4 we are now requiring the `composer-runtime-api` with version 2.0.
These means that Shopware is now only installable with Composer 2.
Installation with Composer 1 is no longer possible and supported. 

## New option for plugins
Updating the core dependency `composer/composer` to version 2 enables the possibility to execute composer commands
during the installation, update and uninstallation of a plugin.
If your plugin provides 3rd party dependencies, override the `executeComposerCommands` method in your plugin base class
and return true.
Now on plugin installation and update of the plugin a `composer require` of your plugin will also be executed,
which installs your dependencies to the root vendor directory of Shopware.
On plugin uninstallation a `composer remove` of your plugin will be executed,
which will also remove all your dependencies.
If you ship dependencies with your plugins within the plugin ZIP file, you should now consider using this config instead.
