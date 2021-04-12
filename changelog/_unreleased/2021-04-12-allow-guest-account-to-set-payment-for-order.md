---
title: Allows a guest account to set the payment for an order
author: Steven de Vries
author_email: steven@enrise.com
author_github: @StevendeVries
---
# API
* Changed annotation for `setPayment()` in `Core/Checkout/Order/SalesChannel/SetPaymentOrderRoute.php` to allow a guest account to reinitialize payment when the previous payment failed
