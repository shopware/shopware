---
title: Show login form when Guest reviews product
issue: NEXT-15005
---
# Storefront
* Changed `login` function in `Shopware\Storefront\Controller\AuthController` to allow guest account to login.
* Changed block `page_product_detail_review_form_container` in `src/Storefront/Resources/views/storefront/page/product-detail/review/review.html.twig` to hide review form and show login form for guest user.
