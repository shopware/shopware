---
title: Preserve focus state after variant switch page reload
issue: NEXT-26705
---
# Storefront
* Changed `variant-switch.plugin.js` and implement `window.focusHandler` to resume the last focus after variant switch page reload.
* Added new JS-plugin option `focusHandlerKey` to `variant-switch.plugin.js`.
