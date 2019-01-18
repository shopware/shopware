import { Application } from 'src/core/shopware';
import 'src/module/sw-settings-rule/component/sw-condition-billing-country';
import 'src/module/sw-settings-rule/component/sw-condition-billing-street';
import 'src/module/sw-settings-rule/component/sw-condition-billing-zip-code';
import 'src/module/sw-settings-rule/component/sw-condition-cart-amount';
import 'src/module/sw-settings-rule/component/sw-condition-currency';
import 'src/module/sw-settings-rule/component/sw-condition-customer-group';
import 'src/module/sw-settings-rule/component/sw-condition-customer-number';
import 'src/module/sw-settings-rule/component/sw-condition-date-range';
import 'src/module/sw-settings-rule/component/sw-condition-different-addresses';
import 'src/module/sw-settings-rule/component/sw-condition-goods-count';
import 'src/module/sw-settings-rule/component/sw-condition-goods-price';
import 'src/module/sw-settings-rule/component/sw-condition-is-new-customer';
import 'src/module/sw-settings-rule/component/sw-condition-last-name';
import 'src/module/sw-settings-rule/component/sw-condition-line-item';
import 'src/module/sw-settings-rule/component/sw-condition-line-item-of-type';
import 'src/module/sw-settings-rule/component/sw-condition-line-item-total-price';
import 'src/module/sw-settings-rule/component/sw-condition-line-item-unit-price';
import 'src/module/sw-settings-rule/component/sw-condition-line-item-with-quantity';
import 'src/module/sw-settings-rule/component/sw-condition-line-items-in-cart';
import 'src/module/sw-settings-rule/component/sw-condition-sales-channel';
import 'src/module/sw-settings-rule/component/sw-condition-shipping-country';
import 'src/module/sw-settings-rule/component/sw-condition-shipping-street';
import 'src/module/sw-settings-rule/component/sw-condition-shipping-zip-code';

Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.addCondition('dateRange', {
        component: 'sw-condition-date-range',
        label: 'global.sw-condition.condition.dateRangeRule.label'
    });
    ruleConditionService.addCondition('salesChannel', {
        component: 'sw-condition-sales-channel',
        label: 'global.sw-condition.condition.salesChannelRule'
    });
    ruleConditionService.addCondition('currency', {
        component: 'sw-condition-currency',
        label: 'global.sw-condition.condition.currencyRule'
    });
    ruleConditionService.addCondition('customerBillingCountry', {
        component: 'sw-condition-billing-country',
        label: 'global.sw-condition.condition.billingCountryRule'
    });
    ruleConditionService.addCondition('customerBillingStreet', {
        component: 'sw-condition-billing-street',
        label: 'global.sw-condition.condition.billingStreetRule'
    });
    ruleConditionService.addCondition('customerBillingZipCode', {
        component: 'sw-condition-billing-zip-code',
        label: 'global.sw-condition.condition.billingZipCodeRule'
    });
    ruleConditionService.addCondition('customerCustomerGroup', {
        component: 'sw-condition-customer-group',
        label: 'global.sw-condition.condition.customerGroupRule'
    });
    ruleConditionService.addCondition('customerCustomerNumber', {
        component: 'sw-condition-customer-number',
        label: 'global.sw-condition.condition.customerNumberRule'
    });
    ruleConditionService.addCondition('customerDifferentAddresses', {
        component: 'sw-condition-different-addresses',
        label: 'global.sw-condition.condition.differentAddressesRule'
    });
    ruleConditionService.addCondition('customerIsNewCustomer', {
        component: 'sw-condition-is-new-customer',
        label: 'global.sw-condition.condition.isNewCustomerRule'
    });
    ruleConditionService.addCondition('customerLastName', {
        component: 'sw-condition-last-name',
        label: 'global.sw-condition.condition.lastNameRule'
    });
    ruleConditionService.addCondition('customerShippingCountry', {
        component: 'sw-condition-shipping-country',
        label: 'global.sw-condition.condition.shippingCountryRule'
    });
    ruleConditionService.addCondition('customerShippingStreet', {
        component: 'sw-condition-shipping-street',
        label: 'global.sw-condition.condition.shippingStreetRule'
    });
    ruleConditionService.addCondition('customerShippingZipCode', {
        component: 'sw-condition-shipping-zip-code',
        label: 'global.sw-condition.condition.shippingZipCodeRule'
    });
    ruleConditionService.addCondition('cartCartAmount', {
        component: 'sw-condition-cart-amount',
        label: 'global.sw-condition.condition.cartAmountRule'
    });
    ruleConditionService.addCondition('cartGoodsCount', {
        component: 'sw-condition-goods-count',
        label: 'global.sw-condition.condition.goodsCountRule'
    });
    ruleConditionService.addCondition('cartGoodsPrice', {
        component: 'sw-condition-goods-price',
        label: 'global.sw-condition.condition.goodsPriceRule'
    });
    ruleConditionService.addCondition('cartLineItemOfType', {
        component: 'sw-condition-line-item-of-type',
        label: 'global.sw-condition.condition.lineItemOfTypeRule.label'
    });
    ruleConditionService.addCondition('cartLineItem', {
        component: 'sw-condition-line-item',
        label: 'global.sw-condition.condition.lineItemRule'
    });
    ruleConditionService.addCondition('cartLineItemsInCart', {
        component: 'sw-condition-line-items-in-cart',
        label: 'global.sw-condition.condition.lineItemsInCartRule'
    });
    ruleConditionService.addCondition('cartLineItemTotalPrice', {
        component: 'sw-condition-line-item-total-price',
        label: 'global.sw-condition.condition.lineItemTotalPriceRule'
    });
    ruleConditionService.addCondition('cartLineItemUnitPrice', {
        component: 'sw-condition-line-item-unit-price',
        label: 'global.sw-condition.condition.lineItemUnitPriceRule'
    });
    ruleConditionService.addCondition('cartLineItemWithQuantity', {
        component: 'sw-condition-line-item-with-quantity',
        label: 'global.sw-condition.condition.lineItemWithQuantityRule'
    });
    ruleConditionService.addCondition('andContainer', {
        component: 'sw-condition-and-container',
        label: 'global.sw-condition.condition.andRule'
    });
    ruleConditionService.addCondition('orContainer', {
        component: 'sw-condition-or-container',
        label: 'global.sw-condition.condition.orRule'
    });
    return ruleConditionService;
});
