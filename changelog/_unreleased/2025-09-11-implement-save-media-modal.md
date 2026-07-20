---
title: Implement save media modal
author: Quynh Nguyen
author_email: q.nguyen@shopware.com
author_github: @quynhnguyen68
---
# Administration
* Added component `sw-media-save-modal` in module `sw-media`
    * This component can be open by `ui.mediaModal.openSaveMedia` command of meteor admin SDK
    * After select a folder, a new media entity is created with selected folderId and passing to callback function these parameters (file name, folderId, mediaId). User save new media with provided mediaId from callback function and upload media Admin API.
* Changed in `src/app/component/structure/sw-media-modal-renderer/index.ts`
    * Added method `onSaveMedia`
    * Added method `closeSaveModal`
    * Added method `saveMediaModal`
* Added component `sw-media-save-modal` in `src/app/component/structure/sw-media-modal-renderer/sw-media-modal-renderer.html.twig`
* Added props `allowCreateFolder` and `disabled` in `src/module/sw-media/component/sw-media-library/index.js`.
* Changed in `src/app/component/media/sw-media-base-item/index.js`
    * Added props `disabled`
    * Changed method `handleItemClick` to prevent clicking if `disabled` is true.
* Changed in `src/module/sw-media/component/sw-media-breadcrumbs/index.js`
    * Added props `disabled`
    * Changed method `onBreadcrumbsItemClicked` to prevent clicking if `disabled` is true.
* Changed in `src/module/sw-media/component/sw-media-library/index.js`
    * Added props `disabled`
    * Added props `allowCreateFolder` to show `Add new folder` button

