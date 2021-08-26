---
title: Add missing `data-form-csrf-handler="true"` to delete product from wishlist
issue:
flag:
author: Adam Sprada
author_email: adam@sprada.pl
author_github: asprada
___
# Storefront
* Added missing data attribute `data-form-csrf-handler="true"` in the `src/Storefront/Resources/views/storefront/component/product/card/box-wishlist.html.twig` to be able to delete product from wishlist when csrf mode is set to ajax.
