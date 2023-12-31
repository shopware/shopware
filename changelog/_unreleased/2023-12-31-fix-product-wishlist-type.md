---
title:              Fix 'wishlists' variable type in product entity
issue:              
author:             Egor Becker
author_email:       niro0842@gmail.com
author_github:      @niro08
---

# Core
*  `wishlists` variable type changed from `Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistCollection` to `Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct\CustomerWishlistProductCollection` in `ProductEntity` class in order to comply with the `ProductDefinition` and prevent TypeError when trying to load a product with the `wishlists` association
