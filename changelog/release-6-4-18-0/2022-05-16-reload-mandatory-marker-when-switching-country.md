---
title: Reload mandatory marker when switching country
issue: NEXT-21490
---
# Storefront
* Added JSON property `zipcodeRequired` for route `frontend.country.country.data` in `Storefront/Controller/CountryStateController.php`
* Added method `updateRequiredZipcode`  in `Storefront/Resources/app/storefront/src/plugin/forms/form-country-state-select.plugin.js` to handle reloading marker for postal code field when switching country
* Changed method `updateStateSelect` in `Storefront/Resources/app/storefront/src/plugin/forms/form-country-state-select.plugin.js` to handle adding/remove * in zipcodeField
* Changed block `component_address_form_zipcode_label` and `component_address_form_zipcode_input` in `Storefront/Resources/views/storefront/component/address/address-form.html.twig` to handle reloading marker for postal code field when switching country
