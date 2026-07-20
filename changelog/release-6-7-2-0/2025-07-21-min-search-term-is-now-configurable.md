---
title: min search term is now configurable
issue: #10556
---
# Administration
* Changed `isValidTerm` method in the following modules:
    * `src/Administration/Resources/app/administration/src/app/mixin/listing.mixin.ts`
    * `src/Administration/Resources/app/administration/src/module/sw-media/component/sw-media-library/index.js`
* Added `minSearchTermLength` handling in `src/Administration/Resources/app/administration/src/app/service/search-ranking.service.js` module
