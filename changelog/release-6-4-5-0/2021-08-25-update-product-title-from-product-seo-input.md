---
title: Update product title from product seo input
issue: NEXT-12525
---
# Storefront
* Changed block `layout_head_title_inner` in `/storefront/page/product-detail/meta.html.twig` to overwrite the product title if we have data from `page.metaInformation.metaTitle`.
