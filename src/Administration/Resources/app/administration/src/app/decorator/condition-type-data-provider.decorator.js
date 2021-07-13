const { Application } = Shopware;

Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.addCondition('dateRange', {
        component: 'sw-condition-date-range',
        label: 'global.sw-condition.condition.dateRangeRule.label',
        scopes: ['global'],
    });
    ruleConditionService.addCondition('timeRange', {
        component: 'sw-condition-time-range',
        label: 'global.sw-condition.condition.timeRangeRule',
        scopes: ['global'],
    });
    ruleConditionService.addCondition('customerOrderCount', {
        component: 'sw-condition-order-count',
        label: 'global.sw-condition.condition.orderCountRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerDaysSinceLastOrder', {
        component: 'sw-condition-days-since-last-order',
        label: 'global.sw-condition.condition.daysSinceLastOrderRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('salesChannel', {
        component: 'sw-condition-sales-channel',
        label: 'global.sw-condition.condition.salesChannelRule',
        scopes: ['global'],
    });
    ruleConditionService.addCondition('currency', {
        component: 'sw-condition-currency',
        label: 'global.sw-condition.condition.currencyRule',
        scopes: ['global'],
    });
    ruleConditionService.addCondition('customerBillingCountry', {
        component: 'sw-condition-billing-country',
        label: 'global.sw-condition.condition.billingCountryRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerBillingStreet', {
        component: 'sw-condition-billing-street',
        label: 'global.sw-condition.condition.billingStreetRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerBillingZipCode', {
        component: 'sw-condition-billing-zip-code',
        label: 'global.sw-condition.condition.billingZipCodeRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerCustomerGroup', {
        component: 'sw-condition-customer-group',
        label: 'global.sw-condition.condition.customerGroupRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerTag', {
        component: 'sw-condition-customer-tag',
        label: 'global.sw-condition.condition.customerTagRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerCustomerNumber', {
        component: 'sw-condition-customer-number',
        label: 'global.sw-condition.condition.customerNumberRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerDifferentAddresses', {
        component: 'sw-condition-different-addresses',
        label: 'global.sw-condition.condition.differentAddressesRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerIsNewCustomer', {
        component: 'sw-condition-is-new-customer',
        label: 'global.sw-condition.condition.isNewCustomerRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerLastName', {
        component: 'sw-condition-last-name',
        label: 'global.sw-condition.condition.lastNameRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerIsCompany', {
        component: 'sw-condition-is-company',
        label: 'global.sw-condition.condition.isCompanyRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerShippingCountry', {
        component: 'sw-condition-shipping-country',
        label: 'global.sw-condition.condition.shippingCountryRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerShippingStreet', {
        component: 'sw-condition-shipping-street',
        label: 'global.sw-condition.condition.shippingStreetRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerShippingZipCode', {
        component: 'sw-condition-shipping-zip-code',
        label: 'global.sw-condition.condition.shippingZipCodeRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('customerLoggedIn', {
        component: 'sw-condition-customer-logged-in',
        label: 'global.sw-condition.condition.customerLoggedInRule',
        scopes: ['checkout'],
    });
    ruleConditionService.addCondition('cartCartAmount', {
        component: 'sw-condition-cart-amount',
        label: 'global.sw-condition.condition.cartAmountRule',
        scopes: ['cart'],
    });
    ruleConditionService.addCondition('cartGoodsCount', {
        component: 'sw-condition-goods-count',
        label: 'global.sw-condition.condition.goodsCountRule',
        scopes: ['cart'],
    });
    ruleConditionService.addCondition('cartGoodsPrice', {
        component: 'sw-condition-goods-price',
        label: 'global.sw-condition.condition.goodsPriceRule',
        scopes: ['cart'],
    });
    ruleConditionService.addCondition('cartLineItemOfType', {
        component: 'sw-condition-line-item-of-type',
        label: 'global.sw-condition.condition.lineItemOfTypeRule.label',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItem', {
        component: 'sw-condition-line-item',
        label: 'global.sw-condition.condition.lineItemRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemsInCart', {
        component: 'sw-condition-line-items-in-cart',
        label: 'global.sw-condition.condition.lineItemsInCartRule',
        scopes: ['cart'],
    });
    ruleConditionService.addCondition('cartLineItemsInCartCount', {
        component: 'sw-condition-line-items-in-cart-count',
        label: 'global.sw-condition.condition.lineItemsInCartCountRule',
        scopes: ['cart'],
    });
    ruleConditionService.addCondition('cartLineItemTotalPrice', {
        component: 'sw-condition-line-item-total-price',
        label: 'global.sw-condition.condition.lineItemTotalPriceRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemUnitPrice', {
        component: 'sw-condition-line-item-unit-price',
        label: 'global.sw-condition.condition.lineItemUnitPriceRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemWithQuantity', {
        component: 'sw-condition-line-item-with-quantity',
        label: 'global.sw-condition.condition.lineItemWithQuantityRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartHasDeliveryFreeItem', {
        component: 'sw-condition-cart-has-delivery-free-item',
        label: 'global.sw-condition.condition.hasDeliveryFreeItemRule',
        scopes: ['cart'],
    });
    ruleConditionService.addCondition('dayOfWeek', {
        component: 'sw-condition-day-of-week',
        label: 'global.sw-condition.condition.dayOfWeekRule',
        scopes: ['global'],
    });
    ruleConditionService.addCondition('cartWeight', {
        component: 'sw-condition-weight-of-cart',
        label: 'global.sw-condition.condition.weightOfCartRule',
        scopes: ['cart'],
    });
    ruleConditionService.addCondition('cartLineItemTag', {
        component: 'sw-condition-line-item-tag',
        label: 'global.sw-condition.condition.lineItemTagRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('alwaysValid', {
        component: 'sw-condition-is-always-valid',
        label: 'global.sw-condition.condition.alwaysValidRule',
        scopes: ['global'],
    });
    ruleConditionService.addCondition('cartLineItemProperty', {
        component: 'sw-condition-line-item-property',
        label: 'global.sw-condition.condition.lineItemPropertyRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemIsNew', {
        component: 'sw-condition-line-item-is-new',
        label: 'global.sw-condition.condition.lineItemIsNewRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemOfManufacturer', {
        component: 'sw-condition-line-item-of-manufacturer',
        label: 'global.sw-condition.condition.lineItemOfManufacturerRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemPurchasePrice', {
        component: 'sw-condition-line-item-purchase-price',
        label: 'global.sw-condition.condition.lineItemPurchasePriceRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemCreationDate', {
        component: 'sw-condition-line-item-creation-date',
        label: 'global.sw-condition.condition.lineItemCreationDateRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemReleaseDate', {
        component: 'sw-condition-line-item-release-date',
        label: 'global.sw-condition.condition.lineItemReleaseDateRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemClearanceSale', {
        component: 'sw-condition-line-item-clearance-sale',
        label: 'global.sw-condition.condition.lineItemClearanceSale',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemPromoted', {
        component: 'sw-condition-line-item-promoted',
        label: 'global.sw-condition.condition.lineItemPromotedRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemInCategory', {
        component: 'sw-condition-line-item-in-category',
        label: 'global.sw-condition.condition.lineItemInCategoryRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemTaxation', {
        component: 'sw-condition-line-item-taxation',
        label: 'global.sw-condition.condition.lineItemTaxationRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemDimensionWidth', {
        component: 'sw-condition-line-item-dimension-width',
        label: 'global.sw-condition.condition.lineItemDimensionWidthRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemDimensionHeight', {
        component: 'sw-condition-line-item-dimension-height',
        label: 'global.sw-condition.condition.lineItemDimensionHeightRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemDimensionLength', {
        component: 'sw-condition-line-item-dimension-length',
        label: 'global.sw-condition.condition.lineItemDimensionLengthRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemDimensionWeight', {
        component: 'sw-condition-line-item-dimension-weight',
        label: 'global.sw-condition.condition.lineItemDimensionWeightRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemOfManufacturer', {
        component: 'sw-condition-line-item-of-manufacturer',
        label: 'global.sw-condition.condition.lineItemOfManufacturerRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemPurchasePrice', {
        component: 'sw-condition-line-item-purchase-price',
        label: 'global.sw-condition.condition.lineItemPurchasePriceRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemCreationDate', {
        component: 'sw-condition-line-item-creation-date',
        label: 'global.sw-condition.condition.lineItemCreationDateRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemListPrice', {
        component: 'sw-condition-line-item-list-price',
        label: 'global.sw-condition.condition.lineItemListPriceRule',
        scopes: ['lineItem'],
    });
    ruleConditionService.addCondition('cartLineItemCustomField', {
        component: 'sw-condition-line-item-custom-field',
        label: 'global.sw-condition.condition.lineItemCustomFieldRule',
        scopes: ['lineItem'],
    });

    ruleConditionService.addCondition('paymentMethod', {
        component: 'sw-condition-payment-method',
        label: 'global.sw-condition.condition.paymentMethodRule',
        scopes: ['cart'],
    });

    ruleConditionService.addCondition('shippingMethod', {
        component: 'sw-condition-shipping-method',
        label: 'global.sw-condition.condition.shippingMethodRule',
        scopes: ['cart'],
    });

    ruleConditionService.addCondition('cartLineItemGoodsTotal', {
        component: 'sw-condition-line-item-goods-total',
        label: 'global.sw-condition.condition.lineItemGoodsTotalRule',
        scopes: ['lineItem'],
    });

    ruleConditionService.addCondition('customerOrderTotalAmount', {
        component: 'sw-condition-order-total-amount',
        label: 'global.sw-condition.condition.orderTotalAmountRule',
        scopes: ['checkout'],
    });

    return ruleConditionService;
});
