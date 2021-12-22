---
title: Price filter does not work for zero value
issue: NEXT-16348
author: Simon Vorgers
author_email: s.vorgers@shopware.com
author_github: SimonVorgers
---
# Storefront
* Changed method `getPriceFilter()` in `Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber` to allow zero value.
* Changed condition  in `src/Storefront/Resources/views/storefront/component/listing/filter-panel.html.twig` to display price filter when price is zero.