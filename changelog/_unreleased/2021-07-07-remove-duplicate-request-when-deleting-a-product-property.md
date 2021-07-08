---
title: Remove duplicate request when deleting a product property
issue: NEXT-15860
---
# Administration
* Changed method `deleteOption` in `src/app/component/base/sw-property-assignment/index.js` to not call `groupProperties` when triggered
