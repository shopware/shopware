---
title: Fix limit of fetched property groups in product listing filter
issue: NEXT-15383
author: Ruben Jacobs
author_email: ruben6jacobs@gmail.com
---
# Administration
* Added `criteria.setLimit(null);` in `src/Administration/Resources/app/administration/src/module/sw-cms/elements/product-listing/config/index.js` to fetch all property groups
