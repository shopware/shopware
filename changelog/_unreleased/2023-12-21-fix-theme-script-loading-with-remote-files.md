---
title: Fix theme script loading with remote files
issue: NEXT-32696
---

# Storefront
* Changed `Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory` to load only js files from `Resources/app/storefront/dist/storefront/js/{technical-name}/{technical-name}.js`

