---
title: Fix twig source path for icons
issue: NEXT-23464
---
# Core
* Changed `Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigLoaderConfigCompilerPass` to add path `BUNDLE/Resources/app/storefront/dist` to the TwigFileLoader.
___
# Storefront
* Changed `storefront/utilities/icon.html.twig` to only use `dist` directory for icons
* Changed `Shopware\Storefront\Framework\Routing\StorefrontSubscriber::addIconSetConfig` to add path `BUNDLE/Resources/app/storefront/dist` to the TwigFileLoader.
___
# Upgrade Information

## Changed icon.html.twig

We changed the base paths to the icons in the template `Storefront/Resources/views/storefront/utilities/icon.html.twig`
If you have overwritten the block `utilities_icon` please change it as follows:

Before:
```twig
...
{% set icon =  source('@' ~ themeIconConfig[pack].namespace ~ '/../' ~ themeIconConfig[pack].path ~'/'~ name ~ '.svg', ignore_missing = true) %}
...
{% set icon = source('@' ~ namespace ~ '/../app/storefront/dist/assets/icon/'~ pack ~'/'~ name ~'.svg', ignore_missing = true) %}
...
```

After:
```twig
...
{% set icon =  source('@' ~ themeIconConfig[pack].namespace ~ '/' ~ themeIconConfig[pack].path ~'/'~ name ~ '.svg', ignore_missing = true) %}
...
{% set icon = source('@' ~ namespace ~ '/assets/icon/'~ pack ~'/'~ name ~'.svg', ignore_missing = true) %}
...
```
