---
title: Status mail Canceled bug
issue: NEXT-13894
---
# Core
* Changed to stop send canceled mail when the customer did not change the payment method within `setPaymentMethod` of `src/Core/Checkout/Order/SalesChannel/SetPaymentOrderRoute.php`
