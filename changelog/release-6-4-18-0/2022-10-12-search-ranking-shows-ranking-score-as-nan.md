---
title: Fix bug search ranking shows ranking score as NaN
issue: NEXT-22888
---
# Core
* Changed method `addExtensions` in `src/Core/Content/Product/SalesChannel/Listing/ProductListingLoader.php` to overwrite `search` extension to get ranking score
