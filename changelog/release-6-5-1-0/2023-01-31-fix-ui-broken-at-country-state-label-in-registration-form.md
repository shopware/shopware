---
title: Fix UI broken at country state label in registration form
issue: NEXT-25069
---
# Storefront
* Changed `block` `component_address_form_country_state_label` in `src/Storefront/Resources/views/storefront/component/address/address-form.html.twig` to remove mark required. 
* Changed `block` `component_address_form_country` in `src/Storefront/Resources/views/storefront/component/address/address-form.html.twig` to update condition about `initialCountryId`.
* Changed function `updateRequiredState` in `src/Storefront/Resources/app/storefront/src/plugin/forms/form-country-state-select.plugin.js` to replace `innerText` to `textContent`.
