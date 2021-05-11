---
title: Add icon pack definition to theme.json
issue: NEXT-14106
---
# Storefront
* Added `iconSets`-property to `\Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration`.
* Changed `\Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory` to read custom icon sets from theme.json files.
* Changed `\Shopware\Storefront\Framework\Routing\StorefrontSubscriber` to add `themeIconConfig` twig variable.
* Changed `icon.html.twig` and `sw_icon` to automatically resolve custom icon packs.
* Added `\Shopware\Storefront\Framework\App\Template\IconTemplateLoader` to load .svg files in registered icon set paths.
