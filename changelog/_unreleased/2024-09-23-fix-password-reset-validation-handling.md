---
title: Fix password reset validation handling
issue: NEXT-38331
---
# Storefront
* Changed `AuthController::generateAccountRecovery` to catch ConstraintViolationException.
* Changed `src/Storefront/Resources/views/storefront/page/account/profile/recover-password.html.twig` to display form violations.
