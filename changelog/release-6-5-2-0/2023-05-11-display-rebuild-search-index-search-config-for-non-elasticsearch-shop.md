---
title: Display rebuild search index search config for non-elasticsearch shop
issue: NEXT-26649
author: Tam Dao
author_email: t.dao@shopware.com
---
# Administration
* Changed notification method when rebuilding search index successfully in `src/Administration/Resources/app/administration/src/module/sw-settings-search/component/sw-settings-search-search-index/index.js` from `createNotificationInfo` to `createNotificationSuccess`.
* Removed missing tests of `sw-settings-search-view-live-search/index.js` in `src/meta/baseline.ts`.
* Removed unused `feature` injection in `src/Administration/Resources/app/administration/src/module/sw-settings-search/component/sw-settings-search-searchable-content/index.js`.
* Added `storefrontEsEnable` in `src/Administration/Resources/app/administration/index.html.tpl`.
* Added new method `storefrontEsEnable` in `src/Administration/Resources/app/administration/src/module/sw-settings-search/component/sw-settings-search-searchable-content/index.js` to get storefrontEsEnable value.
* Added Rebuild Search Index link, Custom Fields tab, Searchable Content for custom field in `src/Administration/Resources/app/administration/src/module/sw-settings-search/component/sw-settings-search-searchable-content/sw-settings-search-searchable-content.html.twig`
* Added new method `storefrontEsEnable` in `src/Administration/Resources/app/administration/src/module/sw-settings-search/view/sw-settings-search-view-live-search/index.js` to get storefrontEsEnable value.
* Added `sw-settings-search-search-index` component for rebuild search index in `src/Administration/Resources/app/administration/src/module/sw-settings-search/view/sw-settings-search-view-live-search/sw-settings-search-view-live-search.html.twig`
