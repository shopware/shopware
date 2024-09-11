---
title: Do not display filter panel for no filters
issue: NEXT-000
author: Janko
author_email: janko.lukic@creen.io
author_github: Janko
---
# Core
*  Removed `price`, `manufacturer`, `rating`, `shipping-free`, `properties`, `options` and `configurators` aggregations of the `Shopware\Content/Product/SalesChannel/Listing/Processor/AggregationListingProcessor.php` and `Content/Product/SalesChannel/Listing/Filter/PropertyListingFilterHandler.php` if no filterable items are found in order to hide the filter panel in the storefront

