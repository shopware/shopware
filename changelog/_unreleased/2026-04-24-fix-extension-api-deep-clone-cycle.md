---
title: Fix Maximum call stack size exceeded in extension-api-data deep clone on circular entity references
issue: NEXT-00000
author: Y Tran
author_email: nguyenytran06@gmail.com
author_github: nguyenytran
---
# Administration
* Changed `Resources/app/administration/src/core/service/extension-api-data.service.ts` so `deepCloneWithEntity` tracks entities currently being cloned via a shared `WeakSet`. When a cycle is detected (e.g. `order.lineItems[i].order === order`) the original reference is returned instead of recursing, preventing the `Maximum call stack size exceeded` crash on order detail pages containing line items with back-references to their parent.
