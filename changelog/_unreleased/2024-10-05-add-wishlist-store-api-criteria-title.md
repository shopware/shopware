---
title: Add criteria titles to wishlist Store APIs
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Change return type of `\Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria::setTitle` from `void` to `self`
* Add criteria title `wishlist::load-products` to `\Shopware\Core\Checkout\Customer\SalesChannel\LoadWishlistRoute::load`
___
# Storefront
* Add criteria title `wishlist::list` to `\Shopware\Storefront\Controller\WishlistController::ajaxList`
* Add criteria title `wishlist::page` to `\Shopware\Storefront\Page\Wishlist\WishlistPageLoader::load`
