---
title: Fix SalesChannel product group selection for certain filters
issue: NEXT-28685
author: Florian Liebig
author_email: hello@florian-liebig.de
author_github: @florianliebig
---
# Core
* Removed default for hasAvailableFilter in `SalesChannelProductDefinition`
* Adjusted `AdminProductStreamController` to not filter for visibility if there is a visibility filter in request
* Changed `sw-sales-channel-products-assignment-dynamic-product-groups` to use `productStreamPreviewService` instead of regular product api
