---
title: Implement media component with Admin SDK
---
# Administration
* Added component `sw-media-modal-renderer` to render media modal triggered from app.
* Changed `src/app/component/structure/sw-admin/sw-admin.html.twig` to add `sw-media-modal-renderer`.
* Added `media-modal.init` to init media modal store.
* Added `media-modal.store` to store media modal data from apps.
* Changed `store.init` to add `initializeMediaModal` and initialize media modal store.
* Changed `global.types` to add type `MediaModalStore`.
* Changed in `src/app/component/media/sw-media-media-item/index.js`
    * Added `extensionSdkButtons` to get action buttons.
    * Added method `runAppAction` to call callback function of action button.
* Changed in `src/app/component/media/sw-media-media-item/sw-media-media-item.html.twig` to add `sw-app-action-button` component.
