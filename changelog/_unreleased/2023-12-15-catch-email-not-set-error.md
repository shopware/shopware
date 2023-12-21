---
title: Catch email not set error
issue: NEXT-32295
---
# Storefront
* Changed method `Shopware\Storefront\Controller\AccountProfileController::saveEmail` to catch `Throwable` instead of `Exception`.
