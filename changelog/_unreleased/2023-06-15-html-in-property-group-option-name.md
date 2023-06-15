---
title: Allow HTML on property_group_option.name
issue: NEXT-25187
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added `new AllowHtml()` flag to the `name` of entity `property_group_option` in `Core/Content/Property/Aggregate/PropertyGroupOption/PropertyGroupOptionDefinition.php` and `Core/Content/Property/Aggregate/PropertyGroupOptionTranslation/PropertyGroupOptionTranslationDefinition.php`
___
# Administration
* Changed rendering of `property_group_option.translated.name` from `innerText` to embedded and sanitized HTML in `sw-product-properties`, `sw-product-restriction-selection.html.twig`, `sw-product-variants-configurator-prices.html.twig`, `sw-product-variants-delivery-media.html.twig` and `sw-property-list.html.twig`
___
# Storefront
* Changed rendering of `property_group_option.translated.name` from Twig auto-escape to sanitized HTML in `@Storefront/storefront/component/buy-widget/configurator.html.twig`, `@Storefront/storefront/component/product/properties.html.twig`, `@Storefront/storefront/page/product-detail/configurator.html.twig`, `@Storefront/storefront/page/product-detail/configurator/select.html.twig`, `@Storefront/storefront/page/product-detail/properties.html.twig`
