---
title: Use the linked product for order GARAN labels
issue: 20599
---
# Storefront
* Changed `storefront/component/line-item/element/garan-label.html.twig` to use `productId` for order items and retain `referencedId` for cart items, preventing invalid order references from breaking order history. Order items without a linked product omit the GARAN label.
