---
title: Fix bulk edit - when empty custom fields is passed, it wipes out ALL custom fields
issue: NEXT-27675
author: Matheus Gontijo
author_email: matheus@matheusgontijo.com
author_github: Matheus Gontijo
---
# Administration
* Changed `src/Administration/Resources/app/administration/src/module/sw-bulk-edit/service/handler/bulk-edit-product.handler.js` - Transform "undefined" values to "null", as "undefined" is not a valid JSON value.
