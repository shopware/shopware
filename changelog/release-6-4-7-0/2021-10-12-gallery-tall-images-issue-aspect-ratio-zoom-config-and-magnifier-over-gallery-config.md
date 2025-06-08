---
title: Gallery image aspect ratio zoom config (tall images fix) and magnifier over gallery config
issue: NEXT-14809
author: Robert Rypula
author_email: r.rypula@webweit.de
---
# Administration
* Added two parameters `magnifierOverGallery` and `keepAspectRatioOnZoom` to `administration/src/module/sw-cms/elements/image-gallery/index.js`.
* Added default value preset to initConfig method at `administration/src/module/sw-cms/elements/image-gallery/config/index.js`.
* Added block with new checkboxes at `administration\src\module\sw-cms\elements\image-gallery\config\sw-cms-el-config-image-gallery.html.twig`.
* Added new snippets related to checkboxes to `administration\src\module\sw-cms\snippet\*.json`
___
# Storefront
* Added new parameter `keepAspectRatioOnZoom` to the plugin `storefront\src\plugin\magnifier\magnifier.plugin.js`
* Changed methods `_setZoomImageSize(...)`, `calculateZoomImageBackgroundPosition(...)` and `_getOverlaySize(....)` to include new `keepAspectRatioOnZoom` parameter at `storefront\src\plugin\magnifier\magnifier.plugin.js`
* Changed styles - due to bug the overlay was hidden even the magnifier area was not the gallery at `storefront\src\scss\component\_cms-element.scss`
* Added logic that consumes parameters from the administration and passes them to the magnifier plugin at `storefront\element\cms-element-image-gallery.html.twig`
