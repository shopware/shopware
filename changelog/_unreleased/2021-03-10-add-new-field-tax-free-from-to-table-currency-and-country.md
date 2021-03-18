---
title: Add the new field is tax-free from to table currency and country.
issue: NEXT-14114
flag: FEATURE_NEXT_14114
---
# Core
* Added new property `taxFreeFrom` in class `Shopware\Core\System\Country\CountryEntity` which used to define an amount value will be applying for the tax-free based on countries of the user.
* Added new property `taxFreeFrom` in class `Shopware\Core\System\Currency\CurrencyEntity` which used to define an amount value will be applying for the tax-free based on the currency of orders.
