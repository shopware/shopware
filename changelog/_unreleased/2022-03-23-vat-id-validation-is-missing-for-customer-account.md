---
title: VAT-ID validation is missing for customer account
issue: NEXT-17283
---
# Core
* Changed method `change` of class `Shopware\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute` to validate `vatIds`
___
# Storefront
* Changed `vatIds` input to keep the change value instead of original value if `formViolations` has path `/vatIds` at `/Storefront/Resources/views/storefront/component/address/address-personal-vat-id.html.twig`
* Added `violationLabel` to display the field's name at `/Storefront/Resources/views/storefront/utilities/form-violation.html.twig`
