---
title: Fix bug with redirecting to detail page in bulk edit modals
issue: NEXT-17137
--- 
# Administration
* Added `target="_blank"` and `rel="noreferrer noopener"` for `router-link` in `sw-bulk-edit-modal` in these files:
    * `src/module/sw-order/page/sw-order-list/sw-order-list.html.twig`
    * `src/module/sw-product/page/sw-product-list/sw-product-list.html.twig`
