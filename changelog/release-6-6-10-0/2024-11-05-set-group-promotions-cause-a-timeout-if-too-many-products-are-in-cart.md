---
title: Set-Group promotions cause a timeout if too many products are in cart
issue: NEXT-32135
---
# Core
* Changed the `findGroupPackages` method in `src/Core/Checkout/Cart/LineItem/Group/LineItemGroupBuilder.php` to reduce the number of calls to `LineItemQuantitySplitter::split` to prevent timeouts
