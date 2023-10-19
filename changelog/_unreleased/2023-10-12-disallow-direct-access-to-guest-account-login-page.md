---
title: Disallow direct access to guest account login page
issue: NEXT-30947
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Storefront
* Changed `Shopware\Storefront\Controller\AuthController::guestLoginPage` to disallow direct access. Access is only allowed via deeplink url from the checkout.
