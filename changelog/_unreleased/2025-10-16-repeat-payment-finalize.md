---
title: Repeat payment finalize
issue: #12593
---
# Core
* Added `src/Core/Checkout/Payment/Cleanup/CleanupPaymentTokenTask.php` to daily clean up old payment tokens.
* Added the feature flag `REPEATED_PAYMENT_FINALIZE` to enable repeated calls to the `/payment-finalize` step without running into errors.
* Changed `src/Core/Checkout/Payment/Controller/PaymentController.php` to redirect to the finalize url if the payment token was already used.
___
# Upgrade Information
## Multiple payment finalize calls allowed
With the feature flag `REPEATED_PAYMENT_FINALIZE`, the `/payment-finalize` endpoint can now be called multiple times using the same payment token.
If the token has already been consumed, the user will be redirected directly to the finish page instead of triggering a PaymentException.
To support this behavior, a new `consumed` flag has been added to the payment token struct, which indicates if the token has already been processed.
Payment tokens are no longer deleted immediately after use. A new scheduled task automatically removes expired tokens to keep the `payment_token` table clean.
___
# Next Major Version Changes
## Multiple payment finalize calls allowed
Multiple calls to the `/payment-finalize` endpoint using the same payment token are now allowed.
If the token has already been consumed, the user is redirected to the finish page without triggering a PaymentException.
To support this behavior, a new `consumed` flag has been added to the payment token struct, which indicates if the token has already been processed.
Since tokens are no longer deleted after use, a new scheduled task runs daily to remove all expired tokens and keep the system clean.
