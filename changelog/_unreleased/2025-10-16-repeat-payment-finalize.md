---
title: Repeat payment finalize
issue: #12593
---
# Core
* Added `src/Core/Migration/V6_7/Migration1760438732AddConsumedToPaymentToken.php`, which introduces a new consumed column to the payment_token table to track token usage.
* Added `src/Core/Checkout/Payment/Cleanup/CleanupPaymentTokenTask.php`, a scheduled task that runs daily to clean up expired tokens.
* Added the feature flag `REPEATED_PAYMENT_FINALIZE` to enable the new behavior.
* Changed `src/Core/Checkout/Payment/Controller/PaymentController.php` to perform an early return if a token has already been consumed, preventing redundant finalizations.
* Changed `src/Core/Checkout/Payment/Cart/Token/JWTFactoryV2.php and src/Core/Checkout/Payment/Cart/Token/TokenStruct.php` to support the new consumed logic and ensure consistency during token creation and handling.
___
# Upgrade Information
## Payment Token Management
### Database Schema
A new consumed column has been added to payment_token. Run migrations after upgrading.
### Token Finalization
Finalizing the same token multiple times will no longer result in an error, if the token is valid and has already been processed.
## Cleanup Task
A new scheduled task will automatically remove expired and consumed tokens to keep the database clean.
___
# Next Major Version Changes
The feature flag will be removed and the behavior become standard
