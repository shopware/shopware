---
title: Allow price fields with more digits
issue: NEXT-8455
author: Timo Altholtmann
---
# Administration
* Changed the currency filter to display up to 20 fraction digits by default
* Added new parameter `additionalOptions` to currency filter
* Changed fields holding a price to support up to 20 digits
* Added property `allowEdit` to `sw-product-seo-form`
* Changed `sw-price-field` component to calculate the linked price on blur
* Removed method `onPriceGrossChangeDebounce` from `sw-price-field` component
* Removed method `onPriceNetChangeDebounce` from `sw-price-field` component
* Removed constant `utils` from `sw-price-field` component
___
# Upgrade Information
## Currency filter
The default of the currency filter in the administration changed.
It will now display by default 2 fraction digits and up to 20, if available.
### Before
* value is 15.123456 -> output is 15.12
* value is 15.12345678913245 -> output is 15.12
### After
* value is 15.123456 -> output is 15.123456
* value is 15.12345678913245 -> output is 15.12345678913245
    
