---
title: Improve LineItemRule and LineItemWithQuantityRule to check on product parent id
issue: NEXT-14707
author: Krispin Lütjann
author_email: k.luetjann@shopware.com
author_github: King-of-Babylon
---
# Core
* Changed LineItemRule and LineItemWithQuantityRule to check on product parent id
* Added `parentId` value to product payload in `Content/Product/Cart/ProductCartProcessor.php`
