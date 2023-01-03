---
title: Update Storefront design
issue: NEXT-23974
---
# Storefront
* Changed deprecated default colors in favor of new colors in `Resources/app/storefront/src/scss/skin/shopware/abstract/variables/_theme.scss` and `src/Storefront/Resources/theme.json`
    * Value of `sw-color-brand-primary` is now `#0B539B`
    * Value of `sw-color-brand-secondary` is now `#3D444D`
    * Value of `sw-color-price` is now `#2B3136`
    * Value of `sw-text-color` is now `#2B3136`
    * Value of `sw-headline-color` is now `#2B3136`
    * Value of `sw-border-color` is now `#798490`
    * Value of `sw-color-buy-button` is now `#0B539B`
* Changed deprecated  default border radius in `Resources/app/storefront/src/scss/skin/shopware/abstract/variables/_bootstrap.scss`
    * Value of `$border-radius` is now `0`
    * Value of `$border-radius-lg` is now `0`
    * Value of `$border-radius-sm` is now `0`
* Changed deprecated values of custom variables in `Resources/app/storefront/src/scss/abstract/variables/_custom.scss`
    * Value of `$icon-base-color` is now `#4a545b`
* Changed current appearance/styling of the pagination in `app/storefront/src/scss/component/_pagination.scss`
    * Borders around pagination items is now removed
