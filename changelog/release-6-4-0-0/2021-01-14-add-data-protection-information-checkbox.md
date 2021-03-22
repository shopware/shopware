---
title: Add Data Protection Information checkbox
issue: NEXT-9291
author: Rune Laenen
author_email: rune@laenen.me 
author_github: runelaenen
---
# Core
* Added `core.loginRegistration.requireDataProtectionCheckbox` configuration option, defaults to false.
* Added `acceptedDataProtection` parameter to `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute`
* Added `acceptedDataProtection` validation rule if `core.loginRegistration.requireDataProtectionCheckbox` configuration is enabled
___
# Storefront
* Added blocks `component_privacy_dpi`, `component_privacy_dpi_checkbox` and `component_privacy_dpi_label` in `@Storefront/storefront/component/privacy-notice.html.twig`
