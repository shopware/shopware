---
title: Add guest login functionality to OrderRoute
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: mstegmeyer
---
# Core
* Added `Shopware\Core\Checkout\Customer\Service\GuestAuthenticator` to unify guest login logic between OrderRoute and DocumentRoute
* Added parameter `login` to `/store-api/order` to allow guest login via DeepLinkCode, it will return a `sw-context-token` in the response headers.
