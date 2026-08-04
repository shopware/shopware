---
title: Add login registration settings Store API route
issue: #18557
---
# API
* Added new Store API route `GET /store-api/login-registration-settings`, which exposes the UI- and validation-relevant subset of the `core.loginRegistration.*` system config (e.g. `passwordMinLength`, `showSalutation`, `requireEmailConfirmation`) resolved for the current sales channel, so headless frontends can render registration forms consistently with the settings configured under Settings > Log-in & sign-up.
___
# Core
* Added `Shopware\Core\Checkout\Customer\SalesChannel\AbstractLoginRegistrationSettingsRoute` as a decoratable extension point.
* Added `Shopware\Core\Checkout\Customer\SalesChannel\LoginRegistrationSettingsRoute`.
* Added `Shopware\Core\Checkout\Customer\SalesChannel\LoginRegistrationSettingsRouteResponse` and `Shopware\Core\Checkout\Customer\SalesChannel\LoginRegistrationSettings`.
