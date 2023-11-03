---
title: Fixing add product by number
issue: NEXT-30079
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: @CR0YD
---
# Storefront
* Changed method `addProductByNumber` in `src/Storefront/Controller/CartLineItemController` to include only products without variants and product variants
