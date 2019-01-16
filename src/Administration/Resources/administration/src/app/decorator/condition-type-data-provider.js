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

Application.addServiceProviderDecorator('ruleConditionService', (ruleConditionService) => {
    ruleConditionService.addCondition('swDateRange', {
        component: 'sw-condition-date-range',
        label: 'global.sw-condition.condition.dateRangeRule.label'
    });
    ruleConditionService.addCondition('swSalesChannel', {
        component: 'sw-condition-sales-channel',
        label: 'global.sw-condition.condition.salesChannelRule'
    });
    ruleConditionService.addCondition('swCurrency', {
        component: 'sw-condition-currency',
        label: 'global.sw-condition.condition.currencyRule'
    });
    ruleConditionService.addCondition('swBillingCountry', {
        component: 'sw-condition-billing-country',
        label: 'global.sw-condition.condition.billingCountryRule'
    });
    ruleConditionService.addCondition('swBillingStreet', {
        component: 'sw-condition-billing-street',
        label: 'global.sw-condition.condition.billingStreetRule'
    });
    ruleConditionService.addCondition('swBillingZipCode', {
        component: 'sw-condition-billing-zip-code',
        label: 'global.sw-condition.condition.billingZipCodeRule'
    });
    ruleConditionService.addCondition('swCustomerGroup', {
        component: 'sw-condition-customer-group',
        label: 'global.sw-condition.condition.customerGroupRule'
    });
    ruleConditionService.addCondition('swCustomerNumber', {
        component: 'sw-condition-customer-number',
        label: 'global.sw-condition.condition.customerNumberRule'
    });
    ruleConditionService.addCondition('swDifferentAddresses', {
        component: 'sw-condition-different-addresses',
        label: 'global.sw-condition.condition.differentAddressesRule'
    });
    ruleConditionService.addCondition('swIsNewCustomer', {
        component: 'sw-condition-is-new-customer',
        label: 'global.sw-condition.condition.isNewCustomerRule'
    });
    ruleConditionService.addCondition('swLastName', {
        component: 'sw-condition-last-name',
        label: 'global.sw-condition.condition.lastNameRule'
    });
    ruleConditionService.addCondition('swShippingCountry', {
        component: 'sw-condition-shipping-country',
        label: 'global.sw-condition.condition.shippingCountryRule'
    });
    ruleConditionService.addCondition('swShippingStreet', {
        component: 'sw-condition-shipping-street',
        label: 'global.sw-condition.condition.shippingStreetRule'
    });
    ruleConditionService.addCondition('swShippingZipCode', {
        component: 'sw-condition-shipping-zip-code',
        label: 'global.sw-condition.condition.shippingZipCodeRule'
    });
    ruleConditionService.addCondition('swCartAmount', {
        component: 'sw-condition-cart-amount',
        label: 'global.sw-condition.condition.cartAmountRule'
    });
    ruleConditionService.addCondition('swGoodsCount', {
        component: 'sw-condition-goods-count',
        label: 'global.sw-condition.condition.goodsCountRule'
    });
    ruleConditionService.addCondition('swGoodsPrice', {
        component: 'sw-condition-goods-price',
        label: 'global.sw-condition.condition.goodsPriceRule'
    });
    ruleConditionService.addCondition('swLineItemOfType', {
        component: 'sw-condition-line-item-of-type',
        label: 'global.sw-condition.condition.lineItemOfTypeRule.label'
    });
    ruleConditionService.addCondition('swLineItem', {
        component: 'sw-condition-line-item',
        label: 'global.sw-condition.condition.lineItemRule'
    });
    ruleConditionService.addCondition('swLineItemsInCart', {
        component: 'sw-condition-line-items-in-cart',
        label: 'global.sw-condition.condition.lineItemsInCartRule'
    });
    ruleConditionService.addCondition('swLineItemTotalPrice', {
        component: 'sw-condition-line-item-total-price',
        label: 'global.sw-condition.condition.lineItemTotalPriceRule'
    });
    ruleConditionService.addCondition('swLineItemUnitPrice', {
        component: 'sw-condition-line-item-unit-price',
        label: 'global.sw-condition.condition.lineItemUnitPriceRule'
    });
    ruleConditionService.addCondition('swLineItemWithQuantity', {
        component: 'sw-condition-line-item-with-quantity',
        label: 'global.sw-condition.condition.lineItemWithQuantityRule'
    });
    ruleConditionService.addCondition('swAndContainer', {
        component: 'sw-condition-and-container',
        label: 'global.sw-condition.condition.andRule'
    });
    ruleConditionService.addCondition('swOrContainer', {
        component: 'sw-condition-or-container',
        label: 'global.sw-condition.condition.orRule'
    });
    return ruleConditionService;
});
