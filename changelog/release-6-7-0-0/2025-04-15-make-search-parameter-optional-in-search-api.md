---
title: Make search parameter optional in search api
issue: 8293
---
# API
* Changed `/store-api/search` to not require `search` parameter
___
# Storefront
* Changed `\Shopware\Storefront\Page\Search\SearchPageLoader::load` to not throw exception when `search` parameter is not given
* Changed the template `src/Storefront/Resources/views/storefront/page/search/index.html.twig` to hide the search result headline if empty search parameter is given
