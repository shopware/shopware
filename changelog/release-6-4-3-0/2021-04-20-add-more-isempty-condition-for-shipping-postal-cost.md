---
title: Add more isEmpty condition for Shipping postal cost
issue: NEXT-12482
---
# Core
* Added new const operators `empty` in `Administration/Resource/app/administration/src/app/service/rule-condition.service.js`
* Added new const `OPERATOR_EMPTY` in `Shopware\Core\Framework\Rule\Rule`
___
# Administration
* Changed component to hide value when select is empty in: 
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-shipping-zip-code`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-days-since-last-order`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-billing-country`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-billing-street`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-billing-zip-code`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-customer-tag`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-last-name`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-shipping-country`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-shipping-street`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-tag`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-of-manufacturer`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-purchase-price`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-release-date`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-in-category`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-dimension-width`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-dimension-height`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-dimension-length`
  `Administration/Resource/app/administration/src/app/component/rule/condition-type/sw-condition-line-item-dimension-weight`
* Changed method `match` to support empty case and method `getConstraints` to change condition validate when create rule in : 
  `Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule`
  `Shopware\Core\Checkout\Customer\Rule\DaysSinceLastOrderRule`
  `Shopware\Core\Checkout\Customer\Rule\BillingCountryRule`
  `Shopware\Core\Checkout\Customer\Rule\BillingStreetRule`
  `Shopware\Core\Checkout\Customer\Rule\BillingZipCodeRule`
  `Shopware\Core\Checkout\Customer\Rule\CustomerTagRule`
  `Shopware\Core\Checkout\Customer\Rule\LastNameRule`
  `Shopware\Core\Checkout\Customer\Rule\ShippingCountryRule`
  `Shopware\Core\Checkout\Customer\Rule\ShippingStreetRule`
  `Shopware\Core\Checkout\Cart\Rule\LineItemTagRule`
  `Shopware\Core\Checkout\Cart\Rule\LineItemOfManufacturerRule`
  `Shopware\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule`
  `Shopware\Core\Checkout\Cart\Rule\LineItemReleaseDateRule`
  `Shopware\Core\Checkout\Cart\Rule\LineItemInCategoryRule`
  `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWidthRule`
  `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionHeightRule`
  `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionLengthRule`
  `Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWeightRule`
