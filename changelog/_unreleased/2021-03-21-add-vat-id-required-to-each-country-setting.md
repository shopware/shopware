---
title: Add VAT id required to each country setting
issue: NEXT-14118
flag: FEATURE_NEXT_14114
---
# Core
* Added new property `vatIdRequired` in class `Shopware\Core\System\Country\CountryEntity`.
* Added `requiredVatIdField` function to `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute` to validate `vatIds` field.
___
# Administration
* Added block `sw_settings_country_detail_content_field_vat_id_required` in `module/sw-settings-country/page/sw-settings-country-detail/sw-settings-country-detail.html.twig`
___
# Storefront
* Changed plugin class `CountryStateSelectPlugin` in `src/Storefront/Resources/app/storefront/src/plugin/forms/form-country-state-select.plugin.js` to add or remove `required` attribute to VAT id field when user changes the country. 
