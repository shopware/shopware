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
    ruleConditionService.addCondition('Shopware\\Core\\Framework\\Rule\\DateRangeRule', {
        component: 'sw-condition-date-range',
        label: 'global.sw-condition-group.condition.dateRangeRule.label'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Framework\\Rule\\SalesChannelRule', {
        component: 'sw-condition-sales-channel',
        label: 'global.sw-condition-group.condition.salesChannelRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Framework\\Rule\\CurrencyRule', {
        component: 'sw-condition-currency',
        label: 'global.sw-condition-group.condition.currencyRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\BillingCountryRule', {
        component: 'sw-condition-billing-country',
        label: 'global.sw-condition-group.condition.billingCountryRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\BillingStreetRule', {
        component: 'sw-condition-billing-street',
        label: 'global.sw-condition-group.condition.billingStreetRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\BillingZipCodeRule', {
        component: 'sw-condition-billing-zip-code',
        label: 'global.sw-condition-group.condition.billingZipCodeRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\CustomerGroupRule', {
        component: 'sw-condition-customer-group',
        label: 'global.sw-condition-group.condition.customerGroupRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\CustomerNumberRule', {
        component: 'sw-condition-customer-number',
        label: 'global.sw-condition-group.condition.customerNumberRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\DifferentAddressesRule', {
        component: 'sw-condition-different-addresses',
        label: 'global.sw-condition-group.condition.differentAddressesRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\IsNewCustomerRule', {
        component: 'sw-condition-is-new-customer',
        label: 'global.sw-condition-group.condition.isNewCustomerRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\LastNameRule', {
        component: 'sw-condition-last-name',
        label: 'global.sw-condition-group.condition.lastNameRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\ShippingCountryRule', {
        component: 'sw-condition-shipping-country',
        label: 'global.sw-condition-group.condition.shippingCountryRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\ShippingStreetRule', {
        component: 'sw-condition-shipping-street',
        label: 'global.sw-condition-group.condition.shippingStreetRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\ShippingZipCodeRule', {
        component: 'sw-condition-shipping-zip-code',
        label: 'global.sw-condition-group.condition.shippingZipCodeRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\CartAmountRule', {
        component: 'sw-condition-cart-amount',
        label: 'global.sw-condition-group.condition.cartAmountRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\GoodsCountRule', {
        component: 'sw-condition-goods-count',
        label: 'global.sw-condition-group.condition.goodsCountRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\GoodsPriceRule', {
        component: 'sw-condition-goods-price',
        label: 'global.sw-condition-group.condition.goodsPriceRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemOfTypeRule', {
        component: 'sw-condition-line-item-of-type',
        label: 'global.sw-condition-group.condition.lineItemOfTypeRule.label'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemRule', {
        component: 'sw-condition-line-item',
        label: 'global.sw-condition-group.condition.lineItemRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemsInCartRule', {
        component: 'sw-condition-line-items-in-cart',
        label: 'global.sw-condition-group.condition.lineItemsInCartRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemTotalPriceRule', {
        component: 'sw-condition-line-item-total-price',
        label: 'global.sw-condition-group.condition.lineItemTotalPriceRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemUnitPriceRule', {
        component: 'sw-condition-line-item-unit-price',
        label: 'global.sw-condition-group.condition.lineItemUnitPriceRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemWithQuantityRule', {
        component: 'sw-condition-line-item-with-quantity',
        label: 'global.sw-condition-group.condition.lineItemWithQuantityRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Framework\\Rule\\Container\\AndRule', {
        component: 'sw-condition-and-container',
        label: 'global.sw-condition-group.condition.andRule'
    });
    ruleConditionService.addCondition('Shopware\\Core\\Framework\\Rule\\Container\\OrRule', {
        component: 'sw-condition-or-container',
        label: 'global.sw-condition-group.condition.orRule'
    });
    return ruleConditionService;
});
