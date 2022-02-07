---
title: Fixed checkout offcanvas close button jumping
issue: NEXT-12695
---

# Storefront
* Changed `src/Storefront/Resources/app/storefront/src/scss/layout/_offcanvas-cart.scss`
    * added css relative position to class `.offcanvas-cart .cart-item`
    * make `.cart-item-remove` right alignment
