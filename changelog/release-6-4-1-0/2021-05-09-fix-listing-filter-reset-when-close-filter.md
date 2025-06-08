---
title: Fix last selected filter value is reset when closing filter off-canvas
issue: NEXT-14509
---
# Storefront
* Changed method `_registerEvents` in `src/Storefront/Resources/app/storefront/src/plugin/offcanvas/offcanvas.plugin.js` to use the method `close()` when clicking on close off-canvas button.
