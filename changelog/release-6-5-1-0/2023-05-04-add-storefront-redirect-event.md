---
title: Add Storefront redirect event
issue: NEXT-26441
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Storefront
* Added `StorefrontRedirectEvent` which is dispatched when a redirect is triggered in the `StorefrontController` via `redirectToRoute` method and allows manipulation of the redirect route and parameters before redirect via event.
