---
title: Simplify personal company fields
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Deprecated variable `showVatIdField` in `@Storefront/storefront/page/account/profile/index.html.twig` template which will be replaced by `showCompanyFields` which controls the visibility of the company fields, i.e. the company name and VAT ID
* Deprecated unused variable `editMode` in `@Storefront/storefront/component/address/address-personal.html.twig` template
* Deprecated blocks `component_address_personal_company`, `component_address_personal_company_fields`, `component_address_personal_vat_id`, and `component_address_personal_vat_id_fields`  in `@Storefront/storefront/component/address/address-personal.html.twig` template use `component_address_personal_company_name` or `component_address_personal_company_vat_id` instead
* Added block `component_address_personal_company_section` in `@Storefront/storefront/component/address/address-personal.html.twig` template
* Changed `@Storefront/storefront/component/address/address-personal.html.twig` template to show VAT ID field next to the company name field instead of below it
