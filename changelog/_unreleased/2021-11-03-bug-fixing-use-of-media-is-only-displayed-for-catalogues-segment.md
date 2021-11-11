---
title: Bug fixing usage of media does not display for Shopping experiences
issue: NEXT-18092
---
# Administration
* Changed `_checkInUsage` method in `src/app/component/media/sw-media-modal-delete/index.js` to check if media used in Shopping experience or not
* Changed `_getUsages` property and added new `loadLayoutAssociations` method in `src/module/sw-media/component/sidebar/sw-media-quickinfo-usage/index.js` to get usage of medias in Shopping experiences
* Changed `_loadItems` method in `src/module/sw-media/component/sw-media-library/index.js` to load more associations
