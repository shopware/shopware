---
title: Fix for deleting value exclusions breaks variant generation
issue: NEXT-23571
author: p.dinkhoff
---
# Administration
* Added a short if-statement in the`sw-product-variants-configurator-restrictions/index.js`, that checks if the variantRestrictions are empty. If they are empty, the variantRestrictions are set to null.
* Added corresponding jest tests
* Removed this file from the `baseline.ts`
