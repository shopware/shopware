---
title: Check redirectTo param is string
issue: NEXT-32680
---
# Storefront
* Changed method `Shopware\Storefront\Controller\StorefrontController::createActionResponse` to check that the `redirectTo` param is a string, otherwise redirect to `/`.
