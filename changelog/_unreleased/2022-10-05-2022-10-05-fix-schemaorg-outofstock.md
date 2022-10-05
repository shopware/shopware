---
title: 2022-10-05-Fix-SchemaOrg-OutOfStock
issue: NEXT-23528
author: Jonas Hess
author_email: jonas@sfxonline.de
author_github: jonas-sfx
---
# Storefront

According to the [Google Merchant Center Help](https://support.google.com/merchants/answer/6324448?hl=en) strucured product data tagged with LimitedAvailability is interpreded as on stock.

In my opinion telling Google that this product is on stock after checking for product.isCloseout and product.availableStock < product.minPurchase is wrong.

*The actual change is only:*

In Twig-Block "component_delivery_information_soldout" I changed
```
<link itemprop="availability" href="http://schema.org/LimitedAvailability"/>
```
to
```
<link itemprop="availability" href="http://schema.org/OutOfStock"/>
```
