---
title: Set local time configuration for date range rule builder
issue: NEXT-14415
---
# Core
* Changed `buildDate` method in `Core/Checkout/Cart/Rule/LineItemReleaseDateRule.php` to remove zero time assignment
* Changed `buildDate` method in `Core/Checkout/Cart/Rule/LineItemCreationDateRule.php` to remove zero time assignment
___
# Administration
* Deprecated `datepickerConfig` in `src/app/component/rule/condition-type/sw-condition-line-item-creation-date/index.js`
* Deprecated `datepickerConfig` in `src/app/component/rule/condition-type/sw-condition-line-item-creation-date/index.js`
* Changed setter of computed property `fromDate` in `src/app/component/rule/condition-type/sw-condition-date-range/index.js` to convert datetime to RFC3399 format
* Changed setter of computed property `toDate` in `src/app/component/rule/condition-type/sw-condition-date-range/index.js` to convert datetime to RFC3399 format
