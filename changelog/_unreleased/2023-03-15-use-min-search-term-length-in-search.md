---
title: Use configured minimum search term length for search
issue: NEXT-25798
author: Tobias Graml
author_email: tobias.graml@nosto.com
author_github: @TobiasGraml11
---

# Administration
* Changed `min` value for `minSearchLength` to 0 at `src/module/sw-settings-search/component/sw-settings-search-search-behaviour/index.js`

# Core
* Added member variable and constructor parameter `productSearchConfigRepository` to the following files:
  * `src/Core/Content/Product/SalesChannel/Search/ProductSearchRoute.php`
  * `src/Core/Content/Product/SalesChannel/Suggest/ProductSuggestRoute.php`
  * `src/Core/Content/Product/SearchKeyword/ProductSearchBuilder.php`
  * `src/Elasticsearch/Product/ProductSearchBuilder.php`
  * `src/Storefront/Controller/SearchController.php`
  * `src/Storefront/Page/Search/SearchPageLoader.php`
* Created `src/Core/Content/Product/Aggregate/ProductSearchConfig/ProductSearchConfigHelper.php`
