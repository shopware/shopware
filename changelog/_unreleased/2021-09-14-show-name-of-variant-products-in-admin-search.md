---
title: Show name of variant products in admin search
issue: NEXT-17015
flag: FEATURE_NEXT_6040
---
# Administration
* Added computed `productDisplayName` in `src/Administration/Resources/app/administration/src/app/component/structure/sw-search-bar-item/index.js` to get name of variant products.
* Changed `loadResults` method in `src/Administration/Resources/app/administration/src/app/component/structure/sw-search-bar/index.js` to update criteria of product. 
* Changed `buildGlobalSearchQueries` method in `src/Administration/Resources/app/administration/src/app/service/search-ranking.service.js` to allow custom Criteria. 
