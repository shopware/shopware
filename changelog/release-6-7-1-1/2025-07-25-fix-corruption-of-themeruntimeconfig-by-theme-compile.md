---
title: Fix corruption of ThemeRuntimeConfig by theme:compile
---
# Core
* Changed `Shopware\Storefront\Theme\ThemeCompiler` to clone StorefrontPluginConfigurationCollection before mutating it.
