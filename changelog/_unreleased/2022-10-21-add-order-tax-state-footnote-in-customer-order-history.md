---
title: Add order tax state footnote in customer's order history
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Storefront
* Added overridable variable `showVatNotice` to `@Storefront/storefront/layout/footer/footer.html.twig` to show or hide the VAT notice. It is still shown by default
* Added override for block `base_footer_inner` to `@Storefront/storefront/page/account/order-history/index.html.twig` and pass `showVatNotice` as false to hide the global VAT notice on order history pages
* Added VAT section to order history item matching the order VAT state. It is wrapped by block `page_account_order_item_detail_table_footnote` after `page_account_order_item_detail_table_labels_summary` in `@Storefront/storefront/page/account/order-history/order-detail.html.twig`
