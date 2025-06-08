---
title: Fix wishlist tries to load filter selection
issue: NEXT-14508
---
# Storefront
* Added a check if `disableEmptyFilter` is defined in `/src/Storefront/Resources/views/storefront/component/product/listing.html.twig` to allow other listing template to override the config `disableEmptyFilterOptions` option
