---
title: Enforce a required birthday in the Store API
---
# Core
* Changed `Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory` to add `NotBlank` to `birthdayDay`, `birthdayMonth` and `birthdayYear` when `core.loginRegistration.showBirthdayField` and `core.loginRegistration.birthdayFieldRequired` are active. `POST /store-api/account/register` and `POST /store-api/account/change-profile` now reject a request without a birthday with `VIOLATION::IS_BLANK_ERROR` on the missing field, as in 6.7. Headless frontends must send the birthday when the setting is active.
