const { Application } = Shopware;

Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.addCondition('dateRange', {
        component: 'sw-condition-date-range',
        label: 'global.sw-condition.condition.dateRangeRule.label',
        scopes: ['global']
    });
    ruleConditionService.addCondition('timeRange', {
        component: 'sw-condition-time-range',
        label: 'global.sw-condition.condition.timeRangeRule',
        scopes: ['global']
    });
    ruleConditionService.addCondition('customerOrderCount', {
        component: 'sw-condition-order-count',
        label: 'global.sw-condition.condition.orderCountRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerDaysSinceLastOrder', {
        component: 'sw-condition-days-since-last-order',
        label: 'global.sw-condition.condition.daysSinceLastOrderRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('salesChannel', {
        component: 'sw-condition-sales-channel',
        label: 'global.sw-condition.condition.salesChannelRule',
        scopes: ['global']
    });
    ruleConditionService.addCondition('currency', {
        component: 'sw-condition-currency',
        label: 'global.sw-condition.condition.currencyRule',
        scopes: ['global']
    });
    ruleConditionService.addCondition('customerBillingCountry', {
        component: 'sw-condition-billing-country',
        label: 'global.sw-condition.condition.billingCountryRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerBillingStreet', {
        component: 'sw-condition-billing-street',
        label: 'global.sw-condition.condition.billingStreetRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerBillingZipCode', {
        component: 'sw-condition-billing-zip-code',
        label: 'global.sw-condition.condition.billingZipCodeRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerCustomerGroup', {
        component: 'sw-condition-customer-group',
        label: 'global.sw-condition.condition.customerGroupRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerCustomerNumber', {
        component: 'sw-condition-customer-number',
        label: 'global.sw-condition.condition.customerNumberRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerDifferentAddresses', {
        component: 'sw-condition-different-addresses',
        label: 'global.sw-condition.condition.differentAddressesRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerIsNewCustomer', {
        component: 'sw-condition-is-new-customer',
        label: 'global.sw-condition.condition.isNewCustomerRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerLastName', {
        component: 'sw-condition-last-name',
        label: 'global.sw-condition.condition.lastNameRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerIsCompany', {
        component: 'sw-condition-is-company',
        label: 'global.sw-condition.condition.isCompanyRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerShippingCountry', {
        component: 'sw-condition-shipping-country',
        label: 'global.sw-condition.condition.shippingCountryRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerShippingStreet', {
        component: 'sw-condition-shipping-street',
        label: 'global.sw-condition.condition.shippingStreetRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('customerShippingZipCode', {
        component: 'sw-condition-shipping-zip-code',
        label: 'global.sw-condition.condition.shippingZipCodeRule',
        scopes: ['checkout']
    });
    ruleConditionService.addCondition('cartCartAmount', {
        component: 'sw-condition-cart-amount',
        label: 'global.sw-condition.condition.cartAmountRule',
        scopes: ['cart']
    });
    ruleConditionService.addCondition('cartGoodsCount', {
        component: 'sw-condition-goods-count',
        label: 'global.sw-condition.condition.goodsCountRule',
        scopes: ['cart']
    });
    ruleConditionService.addCondition('cartGoodsPrice', {
        component: 'sw-condition-goods-price',
        label: 'global.sw-condition.condition.goodsPriceRule',
        scopes: ['cart']
    });
    ruleConditionService.addCondition('cartLineItemOfType', {
        component: 'sw-condition-line-item-of-type',
        label: 'global.sw-condition.condition.lineItemOfTypeRule.label',
        scopes: ['lineItem']
    });
    ruleConditionService.addCondition('cartLineItem', {
        component: 'sw-condition-line-item',
        label: 'global.sw-condition.condition.lineItemRule',
        scopes: ['lineItem']
    });
    ruleConditionService.addCondition('cartLineItemsInCart', {
        component: 'sw-condition-line-items-in-cart',
        label: 'global.sw-condition.condition.lineItemsInCartRule',
        scopes: ['cart']
    });
    ruleConditionService.addCondition('cartLineItemsInCartCount', {
        component: 'sw-condition-line-items-in-cart-count',
        label: 'global.sw-condition.condition.lineItemsInCartCountRule',
        scopes: ['cart']
    });
    ruleConditionService.addCondition('cartLineItemTotalPrice', {
        component: 'sw-condition-line-item-total-price',
        label: 'global.sw-condition.condition.lineItemTotalPriceRule',
        scopes: ['lineItem']
    });
    ruleConditionService.addCondition('cartLineItemUnitPrice', {
        component: 'sw-condition-line-item-unit-price',
        label: 'global.sw-condition.condition.lineItemUnitPriceRule',
        scopes: ['lineItem']
    });
    ruleConditionService.addCondition('cartLineItemWithQuantity', {
        component: 'sw-condition-line-item-with-quantity',
        label: 'global.sw-condition.condition.lineItemWithQuantityRule',
        scopes: ['lineItem']
    });
    ruleConditionService.addCondition('cartHasDeliveryFreeItem', {
        component: 'sw-condition-cart-has-delivery-free-item',
        label: 'global.sw-condition.condition.hasDeliveryFreeItemRule',
        scopes: ['cart']
    });
    ruleConditionService.addCondition('dayOfWeek', {
        component: 'sw-condition-day-of-week',
        label: 'global.sw-condition.condition.dayOfWeekRule',
        scopes: ['global']
    });
    ruleConditionService.addCondition('cartWeight', {
        component: 'sw-condition-weight-of-cart',
        label: 'global.sw-condition.condition.weightOfCartRule',
        scopes: ['cart']
    });
    ruleConditionService.addCondition('cartLineItemTag', {
        component: 'sw-condition-line-item-tag',
        label: 'global.sw-condition.condition.lineItemTagRule',
        scopes: ['lineItem']
    });
    return ruleConditionService;
});
