---
title: Improve URL assembling in storefront js listing.plugin
issue: #12525
---
# Storefront
* Changed: Refactored the URL assembling in the `listing.plugin.js` to be more robust and less redundant by using a new internal `_buildUrl` method.