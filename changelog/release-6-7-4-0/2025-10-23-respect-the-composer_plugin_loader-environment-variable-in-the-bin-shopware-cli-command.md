---
title: Respect the `COMPOSER_PLUGIN_LOADER` environment variable in the `bin/shopware` cli command
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---

# Core

* Changed the `bin/shopware` cli "binary" to respect the environment variable `COMPOSER_PLUGIN_LOADER` and use the `Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader` when enabled

___

# Upgrade Information

## Changed behaviour of CLI commands with `COMPOSER_PLUGIN_LOADER` env variable enabled

If you have set the `COMPOSER_PLUGIN_LOADER` environment variable in your system,
you should check if the CLI commands executed with `bin/shopware` or `bin/console` are still working as expected.
The CLI commands are now correctly considering the `COMPOSER_PLUGIN_LOADER` environment variable
and therefore only plugins installed via composer are loaded, which might differ from the previous behaviour.
