---
title: Remove Storefront deprecations
issue: NEXT-32085
---
# Storefront
* Removed deprecated exception class `VerificationHashNotConfiguredException`
* Changed method `Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory::createFromThemeJson` to abstract.
* Changed parameter `$basePath` in `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration` to be no more nullable.
* Changed parameter `$pluginConfigurationFactory` in constructor of `Shopware\Storefront\Theme\ThemeLifecycleService` to be mandatory.
