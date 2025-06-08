---
title: Postal code not mandatory in alternative delivery address
issue: NEXT-32922
---
# Storefront
* Changed `src/plugin/forms/form-country-state-select.plugin.js` to change element selector in required scope.
* Changed `src/Storefront/Resources/views/storefront/component/address/address-form.html.twig` to add the attribute `data-country-state-select-options` with options `scopeElementSelector`.
