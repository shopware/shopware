---
title: VAT is not mandatory in alternative shipping address
issue: NEXT-37997
---
# Core
* Changed `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute::register` to make the VAT field in the alternative shipping address form prioritized.
___
# Storefront
* Changed plugin `src/Storefront/Resources/app/storefront/src/plugin/forms/form-country-state-select.plugin.js` to handle the VAT field in the alternative shipping address form.
