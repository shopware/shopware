---
title: Pass through extensions from IdSearchResult to EntitySearchResult in ProductListingLoader
author: Oliver Skroblin
author_email: oliver@goblin-coders.de
author_github: OliverSkroblin
---

# Core
* Changed `\Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader::_load` method to pass through extensions from the initial `IdSearchResult` to returned `EntitySearchResult`.
