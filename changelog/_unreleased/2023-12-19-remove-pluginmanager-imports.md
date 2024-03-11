---
title: Remove PluginManager imports and replace with window
issue: NEXT-30176
---
# Storefront
* Changed all ES imports of `PluginManager` to `window.PluginManager` in order to avoid multiple instances of the `PluginManager` in the following JS-plugins:
    * `Resources/app/storefront/src/plugin/add-to-cart/add-to-cart.plugin.js`
    * `Resources/app/storefront/src/plugin/slider/base-slider.plugin.js`
    * `Resources/app/storefront/src/plugin/zoom-modal/zoom-modal.plugin.js`
    * `Resources/app/storefront/src/plugin/ajax-modal/ajax-modal.plugin.js`
