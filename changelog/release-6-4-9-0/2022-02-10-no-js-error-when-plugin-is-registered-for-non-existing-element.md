---
title: No JS error when plugin is registered for non existing element
issue: NEXT-20128
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed `_queryElements` in `plugin.manager.js` to return an empty array in case the element was not found. Before this it sometimes returned an array with `null`.
