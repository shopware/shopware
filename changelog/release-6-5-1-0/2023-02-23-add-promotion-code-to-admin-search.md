---
title: Add promotion code to admin search
issue: NEXT-24354
---
# Administration
* Changed `_groupFields` method in `src/app/service/search-preferences.service.js`
* Changed `default-search-configuration.js` in `sw-order` module to make the order searchable with the promotion codes
* Changed `default-search-configuration.js` in `sw-promotion-v2` module to make promotion codes searchable
* Changed `src/module/sw-profile/snippet/*`
