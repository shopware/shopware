---
title: Add search sorting for live search
author: Le Nguyen
author_email: nguyenquocdaile@gmail.com
author_github: @Le Nguyen
---
# Core
* Changed `proxy` method in `Shopware\Core\Framework\Api\Controller\SalesChannelProxyController` to add `explain-mode` state to the context of the preview search request.
* Changed `get` method in `Shopware\Core\System\SalesChannel\Context\SalesChannelContextService` to add states from original context to the new created context.
___
# Administration
* Added `fetchProductSortings` method to get the product sortings in `src/module/sw-settings-search/component/sw-settings-search-live-search/index.js`.
* Changed `searchOnStorefront` method to use the product sortings in `src/module/sw-settings-search/component/sw-settings-search-live-search/index.js`.
* Changed `sw_settings_search_view_live_search_input` block to add a sorting selection in `src/Administration/Resources/app/administration/src/module/sw-settings-search/component/sw-settings-search-live-search/sw-settings-search-live-search.html.twig`.
* Changed `LiveSearchService` api service to add a `order` payload to the request in `src/module/sw-settings-search/service/livesearch.api.service.js`.
