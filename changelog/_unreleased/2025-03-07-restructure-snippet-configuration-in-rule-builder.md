---
title: Restructure snippet configuration in Rule Builder
issue: NEXT-40678
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: @Simon Fiebranz
---
# Administration
* Deprecated the `genericRuleCondition` util. It will be removed with v6.8.0.
* Added method `getPlaceholder` to `sw-condition-base`.
* Added `snippets.label` property to `condition` type in `rule-condition.service.ts`.
___
# Upgrade Information
## New way of configuring snippets when adding a rule
We changed the snippet configuration of rules in the administration so that the whole configuration takes place when adding a rule via `RuleConditionService.addCondition`.  
Now a condition object also takes the `snippets` property in which all relevant snippet paths like the general rule label can be configured.  
There is also the option now to configure the placeholders of certain fields of the rule via the `snippets.fields.<fieldName>.plceholder`.  
As some rules do not configure the fields themselves but instead are relying on specific components, the fields of these components got static `<fieldNames>` which will be listed below.
### Static field names
- `sw-condition-billing-zip-code`
  - Field to enter alphanumeric zip codes -> `alphanumericZipCodes`
  - Field to enter numeric zip codes -> `numericZipCodes`
- `sw-condition-customer-custom-field`
  - Field to select the custom field -> `customField`
  - Field to enter value for the custom field -> Type of the custom field (e.g. `int`, `string`, `datetime`, ...)
- `sw-date-range`
  - Field to select with or without timestamp -> `withTime`
  - Field to enter the start date -> `fromDate`
  - Field to enter the end date -> `toDate`
- `sw-condition-goods-count`
  - Field to enter the count -> `goodsCount`
- `sw-condition-goods-price`
  - Field to enter the price -> `goodsPrice`
- `sw-always-valid`
  - Field to select always valid value -> `alwaysValid`
- `sw-condition-line-item`
  - Field to select the products -> `products`
- `sw-condition-line-item-custom-field`
  - Field to select the custom field -> `customField`
  - Field to enter value for the custom field -> Type of the custom field (e.g. `int`, `string`, `datetime`, ...)
- `sw-condition-line-item-goods-total`
  - Field to select the total -> `goodsTotal`
- `sw-condition-line-item-in-category`
  - Field to select the categories -> `categories`
- `sw-condition-line-item-property`
  - Field to select the product properties -> `properties`
- `sw-condition-line-item-purchase-price`
  - Field to enter the purchase price -> `purchasePrice`
- `sw-condition-line-item-with-quantity`
  - Field to select the product -> `product`
  - Field to select the quantity -> `quantity`
- `sw-condition-order-custom-field`
  - Field to select the custom field -> `customField`
  - Field to enter value for the custom field -> Type of the custom field (e.g. `int`, `string`, `datetime`, ...)
- `sw-shipping-code-zip-code`
  - Field to enter alphanumeric zip codes -> `alphanumericZipCodes`
  - Field to enter numeric zip codes -> `numericZipCodes`
- `sw-time-range`
  - Field to enter the start date -> `fromTime`
  - Field to enter the end date -> `toTime`
