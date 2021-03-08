---
title: Fix only allow company registration does not work
issue: NEXT-13584
---
# Storefront
* Added new variable `hasSelectedBusiness` in `platform/src/Storefront/Resources/views/storefront/component/account/customer-group-register.html.twig` to show `component_account_register_company_fields` block.
* Added new variable `onlyCompanyRegistration` in `src/Storefront/Resources/views/storefront/component/address/address-personal.html.twig` to conditionally show company fields and disable `accountType` selection if `onlyCompanyRegistration` is true.
