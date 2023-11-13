---
title: New option for plugins using Composer 2
issue: NEXT-1797
author: Michael Telgmann
author_github: @mitelg
---
# Core
* Changed dependency `composer/composer` to version 2
* Added new dependency `composer-runtime-api` with version 2.0
* Added new method `executeComposerCommands` to `Shopware\Core\Framework\Plugin` to enable composer commands during plugin install/update/uninstall
___
# Upgrade Information
## New `executeComposerCommands` option for plugins

If your plugin provides 3rd party dependencies, override the `executeComposerCommands` method in your plugin base class
and return true.
Now on plugin installation and update of the plugin a `composer require` of your plugin will also be executed,
which installs your dependencies to the root vendor directory of Shopware.
On plugin uninstallation a `composer remove` of your plugin will be executed,
which will also remove all your dependencies.
If you ship dependencies with your plugins within the plugin ZIP file, you should now consider using this config instead.
