---
title: Cookie Consent "deny" button touchstart event triggers underneath elements on mobile
issue: NEXT-21887
---
# Storefront
* Changed `_handleDenyButton` method in `src/Storefront/Resources/app/storefront/src/plugin/cookie/cookie-permission.plugin.js` to prevent touchstart event click.
