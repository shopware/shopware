---
title: Replace category media sidebar by media modal
issue: NEXT-22905
---
# Administration
* Removed `sw-sidebar` component in `src/Administration/Resources/app/administration/src/module/sw-category/page/sw-category-detail/sw-category-detail.html.twig`
* Added `sw-media-modal-v2` component in `src/Administration/Resources/app/administration/src/module/sw-category/component/sw-category-detail-menu/sw-category-detail-menu.html.twig` to replace `sw-sidebar-media-item`
* Added methods in `src/Administration/Resources/app/administration/src/module/sw-category/component/sw-category-detail-menu/index.js`
  * `onMediaSelectionChange` to update category media
* Deprecated the following methods in `src/Administration/Resources/app/administration/src/module/sw-category/page/sw-category-detail/index.js`
  * `setMediaFromSidebar`
  * `openMediaSidebar`
* Deprecated component `sw-sidebar` in favor for `sw-media-modal-v2`. The component will be removed in v6.6.0.0.
