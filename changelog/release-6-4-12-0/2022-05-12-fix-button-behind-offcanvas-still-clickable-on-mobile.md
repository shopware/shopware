---
title: Fix button behind off-canvas still clickable on mobile
issue: NEXT-21565
---
# Storefront
* Changed method `_registerEvents` in `src/Storefront/Resources/app/storefront/src/plugin/offcanvas/offcanvas.plugin.js` to register event `touchend` instead of `touchstart` when clicking on close off-canvas button.
