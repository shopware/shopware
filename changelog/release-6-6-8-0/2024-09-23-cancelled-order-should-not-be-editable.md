---
title: Cancelled order should not be editable in the storefront
issue: NEXT-38503
author: Carlo Cecco
author_email: 6672778+luminalpark@users.noreply.github.com
author_github: @luminalpark
---
# Storefront
* Changed `src/Storefront/Page/Account/Order/AccountEditOrderPageLoader.php` to check if order is in cancelled state, in that case throw an exception.
* Added `CHECKOUT__ORDER_ORDER_CANCELLED` snippet to `src/Storefront/Resources/snippet/en_GB/storefront.en-GB.json` to show error message.
* Added `CHECKOUT__ORDER_ORDER_CANCELLED` snippet to `src/Storefront/Resources/snippet/de_DE/storefront.de-DE.json` to show error message.
___
# Core
* Changed `src/Core/Checkout/Order/OrderException.php` to define the exception.
