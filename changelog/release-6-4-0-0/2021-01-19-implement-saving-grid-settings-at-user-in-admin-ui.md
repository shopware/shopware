---
title: Implement saving grid settings at user in admin ui
issue: NEXT-9589
---
# Administration
* Changed logic to save config from localStorage to database.
* Added methods `findUserSetting` in `/app/component/data-grid/sw-data-grid/index.js` to get user config from api.
* Added watch props `compact` and `previews` in `/app/component/data-grid/sw-data-grid-settings/index.js`.
* Added props `identifier` at component `sw-entity-listing` in `/module/sw-manufacturer/page/sw-manufacturer-list/sw-manufacturer-list.html.twig`.
* Added props `identifier` at component `sw-entity-listing` in `/module/sw-product-stream/page/sw-product-stream-list/sw-product-stream-list.html.twig`.
* Added props `identifier` at component `sw-entity-listing` in `/module/sw-promotion/page/sw-promotion-list/sw-promotion-list.html.twig`.
* Added props `identifier` at component `sw-entity-listing` in `/module/sw-review/page/sw-review-list/sw-review-list.html.twig`.
* Deprecated methods `initCompactModeAndShowPreviews` in `/app/component/data-grid/sw-data-grid/index.js`.
