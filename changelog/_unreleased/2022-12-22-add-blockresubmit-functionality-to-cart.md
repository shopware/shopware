---
title: Add blockResubmit functionality to cart validators
issue: [#2898](https://github.com/shopware/platform/issues/2898)
flag: 
author: Altay Akkus
author_email: altayakkus1993@googlemail.com
author_github: @AltayAkkus
---
# Core
*  Added feature `blockResubmit` for cart validators, which should block the order, but not prevent the user from retrying.
*  Added Twig checks for `blockResubmit` in `src/Storefront/Resources/views/storefront/page/checkout/confirm/index.html.twig`.
*  Added `blockResubmit` error collection in `src/Core/Checkout/Cart/Error/ErrorCollection.php`.
___
# Upgrade Information
Backwards compatible, the `blockResubmit` attribute is assumed True if not set.