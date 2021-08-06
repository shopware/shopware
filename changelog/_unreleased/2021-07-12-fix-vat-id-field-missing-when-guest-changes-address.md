---
title: Fix VAT id field missing when guest changes address in checkout
issue: NEXT-15957
flag: FEATURE_NEXT_15957
---
# Storefront
* Changed a function `addressBook` at `Shopware\Storefront\Controller\AddressController` to update VAT id when guest changes address in checkout.
* Changed block `component_address_form_company_vatId` from `/src/Storefront/Resources/views/storefront/component/address/address-personal-company.html.twig` to show VAT id field when guest changes address.
* Deprecated block `component_address_form_company_vatId_label` in `src/Storefront/Resources/views/storefront/component/address/address-form.html.twig`, will be removed in v6.5.0
* Deprecated block `component_address_form_company_vatId_input` in `src/Storefront/Resources/views/storefront/component/address/address-form.html.twig`, will be removed in v6.5.0
* Deprecated block `component_address_form_company_vatId_input_error` in `src/Storefront/Resources/views/storefront/component/address/address-form.html.twig`, will be removed in v6.5.0
