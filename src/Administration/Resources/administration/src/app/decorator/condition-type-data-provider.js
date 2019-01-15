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
    ruleConditionService.addCondition('date_range', {
        component: 'sw-condition-date-range',
        label: 'global.sw-condition.condition.dateRangeRule.label'
    });
    ruleConditionService.addCondition('sales_channel', {
        component: 'sw-condition-sales-channel',
        label: 'global.sw-condition.condition.salesChannelRule'
    });
    ruleConditionService.addCondition('currency', {
        component: 'sw-condition-currency',
        label: 'global.sw-condition.condition.currencyRule'
    });
    ruleConditionService.addCondition('billing_country', {
        component: 'sw-condition-billing-country',
        label: 'global.sw-condition.condition.billingCountryRule'
    });
    ruleConditionService.addCondition('billing_street', {
        component: 'sw-condition-billing-street',
        label: 'global.sw-condition.condition.billingStreetRule'
    });
    ruleConditionService.addCondition('billing_zip_code', {
        component: 'sw-condition-billing-zip-code',
        label: 'global.sw-condition.condition.billingZipCodeRule'
    });
    ruleConditionService.addCondition('customer_group', {
        component: 'sw-condition-customer-group',
        label: 'global.sw-condition.condition.customerGroupRule'
    });
    ruleConditionService.addCondition('customer_number', {
        component: 'sw-condition-customer-number',
        label: 'global.sw-condition.condition.customerNumberRule'
    });
    ruleConditionService.addCondition('different_addresses', {
        component: 'sw-condition-different-addresses',
        label: 'global.sw-condition.condition.differentAddressesRule'
    });
    ruleConditionService.addCondition('is_new_customer', {
        component: 'sw-condition-is-new-customer',
        label: 'global.sw-condition.condition.isNewCustomerRule'
    });
    ruleConditionService.addCondition('last_name', {
        component: 'sw-condition-last-name',
        label: 'global.sw-condition.condition.lastNameRule'
    });
    ruleConditionService.addCondition('shipping_country', {
        component: 'sw-condition-shipping-country',
        label: 'global.sw-condition.condition.shippingCountryRule'
    });
    ruleConditionService.addCondition('shipping_street', {
        component: 'sw-condition-shipping-street',
        label: 'global.sw-condition.condition.shippingStreetRule'
    });
    ruleConditionService.addCondition('shipping_zip_code', {
        component: 'sw-condition-shipping-zip-code',
        label: 'global.sw-condition.condition.shippingZipCodeRule'
    });
    ruleConditionService.addCondition('cart_amount', {
        component: 'sw-condition-cart-amount',
        label: 'global.sw-condition.condition.cartAmountRule'
    });
    ruleConditionService.addCondition('goods_count', {
        component: 'sw-condition-goods-count',
        label: 'global.sw-condition.condition.goodsCountRule'
    });
    ruleConditionService.addCondition('goods_price', {
        component: 'sw-condition-goods-price',
        label: 'global.sw-condition.condition.goodsPriceRule'
    });
    ruleConditionService.addCondition('line_item_of_type', {
        component: 'sw-condition-line-item-of-type',
        label: 'global.sw-condition.condition.lineItemOfTypeRule.label'
    });
    ruleConditionService.addCondition('line_item', {
        component: 'sw-condition-line-item',
        label: 'global.sw-condition.condition.lineItemRule'
    });
    ruleConditionService.addCondition('line_items_in_cart', {
        component: 'sw-condition-line-items-in-cart',
        label: 'global.sw-condition.condition.lineItemsInCartRule'
    });
    ruleConditionService.addCondition('line_item_total_price', {
        component: 'sw-condition-line-item-total-price',
        label: 'global.sw-condition.condition.lineItemTotalPriceRule'
    });
    ruleConditionService.addCondition('line_item_unit_price', {
        component: 'sw-condition-line-item-unit-price',
        label: 'global.sw-condition.condition.lineItemUnitPriceRule'
    });
    ruleConditionService.addCondition('line_item_with_quantity', {
        component: 'sw-condition-line-item-with-quantity',
        label: 'global.sw-condition.condition.lineItemWithQuantityRule'
    });
    ruleConditionService.addCondition('and_container', {
        component: 'sw-condition-and-container',
        label: 'global.sw-condition.condition.andRule'
    });
    ruleConditionService.addCondition('or_container', {
        component: 'sw-condition-or-container',
        label: 'global.sw-condition.condition.orRule'
    });
    return ruleConditionService;
});
