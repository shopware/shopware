---
title: Respect the `COMPOSER_PLUGIN_LOADER` environment variable in the `bin/shopware` cli command
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed the `bin/shopware` cli "binary" to respect the environment variable `COMPOSER_PLUGIN_LOADER` and use the `Shopware\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader` when enabled
