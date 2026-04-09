---
title: Improve error handling of Storefront JS plugin manager
---
# Storefront
* Changed `storefront/src/plugin-system/plugin.manager.js` and `storefront/src/plugin-system/plugin.class.js` to create console warnings instead of throwing exceptions when trying to register or override non existend JS plugins.