---
title: Fixed csrf error and language switch error on 404 pages
issue: NEXT-16632
---
# Storefront
* Changed method `CsrfPlaceholderHandler::replaceCsrfToken` to replace csrf tokens also on responses with status code 404
* Changed method `StorefrontController::createActionResponse` to fallback to redirect to `frontend.home.page` if no `redirectTo` and no `forwardTo` parameter is set.
* Changed method `ContextController::switchLanguage` for route `/checkout/language` to redirect to `frontend.home.page` the `redirectTo` parameter is set to an empty string.
