---
title: Optimize variant listing loading
issue: NEXT-29587
---
# Administration
* Added a new method `getDefaultFolderId` in `media.api.service.js` to get the id of default folder of given entity with memoization.
* Changed method `getDefaultFolderId` in component `sw-media-upload-v2` to get the call `mediaService.getDefaultFolderId` with memoization.
* Changed template `sw-product-modal-variant-generation.html.twig` and `sw-product-variants-overview.html.twig` to inject `target-folder-id` to `sw-media-upload-v2` component instead of `default-folder` to reduce duplicate search folder requests.
