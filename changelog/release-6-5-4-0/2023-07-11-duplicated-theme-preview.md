---
title: Fix duplication of theme images
issue: NEXT-25804
---
# Storefront
* Changed `Shopware\Storefront\DependencyInjection\StorefrontMigrationReplacementCompilerPass` by adding `Shopware\Storefront\Migration\V6_5` to migration directories.
  * Added `Shopware\Storefront\Migration\V6_5\Migration1688644407ThemeAddThemeConfig`
    * Added JSON field `theme_json` to table `theme`
* Added for `v6.6.0` method `createFromThemeJson` to `Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory`
* Added property `themeJson` and getter and setter to `\Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration`
* Added method `createFromThemeJson` to `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory` to create a `StorefrontPluginConfiguration` from the json of the theme in the db.
* Added field `themeJson` to `Shopware\Storefront\Theme\ThemeDefinition`
* Added property `themeJson` to `Shopware\Storefront\Theme\ThemeEntity`
* Changed `\Shopware\Storefront\Theme\ThemeLifecycleService` to compare last installed themeJson version with current to prevent duplicated images.
