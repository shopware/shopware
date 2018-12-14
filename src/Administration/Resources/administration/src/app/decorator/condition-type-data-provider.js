import { Application } from 'src/core/shopware';

Application.addServiceProviderDecorator('ruleConditionService', (ruleConditionService) => {
    ruleConditionService.addCondition('Shopware\\Core\\Framework\\Rule\\DateRangeRule', {
        label: 'global.sw-condition-group.condition.dateRangeRule',
        operatorSet: ruleConditionService.operatorSets.test
    });
    ruleConditionService.addCondition('Shopware\\Core\\Framework\\Rule\\SalesChannelRule', {
        label: 'global.sw-condition-group.condition.salesChannelRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Framework\\Rule\\CurrencyRule', {
        label: 'global.sw-condition-group.condition.currencyRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\BillingCountryRule', {
        label: 'global.sw-condition-group.condition.billingCountryRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\BillingStreetRule', {
        label: 'global.sw-condition-group.condition.billingStreetRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\BillingZipCodeRule', {
        label: 'global.sw-condition-group.condition.billingZipCodeRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\CustomerGroupRule', {
        label: 'global.sw-condition-group.condition.customerGroupRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\CustomerNumberRule', {
        label: 'global.sw-condition-group.condition.customerNumberRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\DifferentAddressRule', {
        label: 'global.sw-condition-group.condition.differentAddressRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\IsNewCustomerRule', {
        label: 'global.sw-condition-group.condition.isNewCustomerRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\LastNameRule', {
        label: 'global.sw-condition-group.condition.lastNameRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\ShippingCountryRule', {
        label: 'global.sw-condition-group.condition.shippingCountryRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\ShippingStreetRule', {
        label: 'global.sw-condition-group.condition.shippingStreetRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\ShippingZipCodeRule', {
        label: 'global.sw-condition-group.condition.shippingZipCodeRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\CartAmountRule', {
        label: 'global.sw-condition-group.condition.cartAmountRule',
        operatorSet: ruleConditionService.operatorSets.defaultSet
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\GoodsCountRule', {
        label: 'global.sw-condition-group.condition.goodsCountRule',
        operatorSet: ruleConditionService.operatorSets.defaultSet
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\GoodsPriceRule', {
        label: 'global.sw-condition-group.condition.goodsPriceRule',
        operatorSet: ruleConditionService.operatorSets.defaultSet
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemOfTypeRule', {
        label: 'global.sw-condition-group.condition.lineItemOfTypeRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemRule', {
        label: 'global.sw-condition-group.condition.lineItemRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemsInCartRule', {
        label: 'global.sw-condition-group.condition.lineItemsInCartRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemTotalPriceRule', {
        label: 'global.sw-condition-group.condition.lineItemTotalPriceRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemUnitPriceRule', {
        label: 'global.sw-condition-group.condition.lineItemUnitPriceRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemWithQuantityPriceRule', {
        label: 'global.sw-condition-group.condition.lineItemWithQuantityRule',
        operatorSet: ruleConditionService.operatorSets.string
    });
    return ruleConditionService;
});
