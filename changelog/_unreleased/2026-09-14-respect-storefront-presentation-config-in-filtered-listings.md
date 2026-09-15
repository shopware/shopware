---
title: Respect storefront presentation config in filtered listings
issue: 13901
author: rittou
author_email: rittou.xiii@gmail.com
author_github: rittou
---
# Core
* Changed `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader::shouldLoadPreviews()` to align preview resolution with the `core.listing.findBestVariant` setting. Listings and search results filtered by variant options or properties now keep using the product's configured storefront preview (main variant or parent) while the setting is disabled, and only fall back to the best matching filtered variant while it is enabled.
* Changed `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader::hasOptionFilter()` to also detect post filters on `properties.id` and `propertyIds`.
* Changed the label and help text of the `core.listing.findBestVariant` system config in `src/Core/System/Resources/config/listing.xml` to describe that it applies to both search results and filtered listings.
