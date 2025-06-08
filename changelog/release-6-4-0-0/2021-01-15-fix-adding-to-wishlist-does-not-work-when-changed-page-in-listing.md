---
title: Adding to wishlist does not work when changed page in listing
issue: NEXT-13132
---
# Storefront
* Changed methods `renderResponse` in `Resources/app/storefront/src/plugin/listing/listing.plugin.js` to added `$emitter.publish` with name `Listing/afterRenderResponse`.
* Changed methods `_registerEvents` in `Resources/app/storefront/src/plugin/header/wishlist-widget.plugin.js` to added `$emitter.subscribe` with name `Listing/afterRenderResponse`.
