---
title: Add greater than and lower than to condition in shipping zipcode rule
issue: NEXT-8349
---
# Core
* Changed method `match` in `Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule`
* Changed method `getConstraints` in `Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule`
___
# Administration
* Changed component `sw-condition-shipping-zip-code` in `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-shipping-zip-code`
* Changed block `sw_condition_value_content` in `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-shipping-zip-code`
* Added a new `zipCode` operator set into operatorSets const `Administration/Resource/app/administration/src/app/service/rule-condition.service.js`
