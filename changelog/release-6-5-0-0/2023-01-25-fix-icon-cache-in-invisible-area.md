---
title: Fixed Icon Cache for invisible areas
issue: NEXT-24529
---
# Storefront
* Added option `IconCache` for `Storefront/Resources/views/storefront/utilities/alert.html.twig` to disable the icon cache for the rendered icon.
* Changed `Storefront/Resources/views/storefront/base.html.twig` by adding `iconCache: 'false'` to the include of the `alert.html.twig` in the noscript area.
* Changed `Storefront/Resources/views/storefront/page/error/error-maintenance.html.twig` by adding `iconCache: 'false'` to the include of the `alert.html.twig` in the noscript area.
* Changed `Storefront/Resources/views/storefront/page/content/single-cms-page.html.twig` by adding `iconCache: 'false'` to the include of the `alert.html.twig` in the noscript area.
