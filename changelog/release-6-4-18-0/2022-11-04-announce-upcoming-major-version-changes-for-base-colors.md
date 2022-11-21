---
title: Announce upcoming major version changes for base colors and appearance
issue: NEXT-23969
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Deprecated current default colors in favor of new colors in `Resources/app/storefront/src/scss/skin/shopware/abstract/variables/_theme.scss` and `src/Storefront/Resources/theme.json`
    * Value of `sw-color-brand-primary` will be `#0B539B`
    * Value of `sw-color-brand-secondary` will be `#3D444D`
    * Value of `sw-color-price` will be `#2B3136`
    * Value of `sw-text-color` will be `#2B3136`
    * Value of `sw-headline-color` will be `#2B3136`
    * Value of `sw-border-color` will be `#798490`
    * Value of `sw-color-buy-button` will be `#0B539B`
* Deprecated default border radius in `Resources/app/storefront/src/scss/skin/shopware/abstract/variables/_bootstrap.scss`
    * Value of `$border-radius` will be `0`
    * Value of `$border-radius-lg` will be `0`
    * Value of `$border-radius-sm` will be `0`
* Deprecated values of custom variables in `Resources/app/storefront/src/scss/abstract/variables/_custom.scss`
    * Value of `$icon-base-color` will be `#798490`
* Deprecated current appearance/styling of the pagination in `app/storefront/src/scss/component/_pagination.scss`
    * Borders around pagination items will be removed
