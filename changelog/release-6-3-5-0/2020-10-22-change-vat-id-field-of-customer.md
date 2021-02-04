---
title: Change VAT ID field of customer
issue: NEXT-11193
---
# Storefront
* Added template `src/Storefront/Resources/views/storefront/component/address/address-personal-company.html.twig`.
* Added template `src/Storefront/Resources/views/storefront/component/address/address-personal-vat-id.html.twig`.
* Added block `page_account_overview_profile_company` in template `src/Storefront/Resources/views/storefront/page/account/index.html.twig`.
* Added block `component_account_register_company_fields` in template `src/Storefront/Resources/views/storefront/component/account/register.html.twig`.
* Added block `component_address_personal_vat_id` in template `src/Storefront/Resources/views/storefront/component/address/address-personal.html.twig`.
___
# Administration
* Added block `sw_customer_card_vat_ids` in template `src/module/sw-customer/component/sw-customer-card/sw-customer-card.html.twig`.
* Added block `sw_customer_base_form_vat_id_field` in template `src/module/sw-customer/component/sw-customer-base-form/sw-customer-base-form.html.twig`.
* Added new scss file `src/module/sw-customer/component/sw-customer-base-form/sw-customer-base-form.scss`.
* Added new scss file `src/module/sw-customer/component/sw-customer-address-form/sw-customer-address-form.scss`.
