---
title: Show main variant in filtered listings only if it matches the active filters
issue: 20904
author: tamvt
author_email: t.vo@shopware.com
author_github: tamvt
---
# Core
* Changed `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader` to show the configured main variant of a variant product in filtered listings only if it matches all active filters, such as property, price or manufacturer filters. Otherwise, a matching variant is shown. Products configured to display their parent always show the parent.
* Changed `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader::shouldLoadPreviews()`, so `core.listing.findBestVariant` only affects search results again. With it enabled, filtered listings show a matching main variant or the parent instead of another matching variant.
* Added the optional `postFilters` property to `Shopware\Core\Content\Product\Extension\LoadPreviewExtension`, so extensions that replace the preview resolution can apply the same rule.
* Changed the label and help text of the `core.listing.findBestVariant` system config in `src/Core/System/Resources/config/listing.xml` to describe that it only applies to search results.
