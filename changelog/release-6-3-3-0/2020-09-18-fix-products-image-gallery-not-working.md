---
title: Fix products image gallery not working
issue: NEXT-10693
---
# Storefront
* Changed the strict mode to false on `DomAccess.getDataAttribute(img, this.options.imgDataSrcSetAttr, false);` in `src/Storefront/Resources/app/storefront/src/plugin/zoom-modal/zoom-modal.plugin.js` so the CMS Image Gallery won't throw error missing `data-srcset` and leads to not working.  
