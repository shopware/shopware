const { Application, Feature } = Shopware;

Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.addAwarenessConfiguration(
        'personaPromotions',
        {
            notEquals: [
                'cartCartAmount',
            ],
            equalsAny: [
                'customerBillingCountry',
                'customerBillingStreet',
                'customerBillingZipCode',
                'customerIsNewCustomer',
                'customerCustomerGroup',
                'customerCustomerNumber',
                'customerDaysSinceLastOrder',
                'customerDifferentAddresses',
                'customerLastName',
                'customerOrderCount',
                'customerShippingCountry',
                'customerShippingStreet',
                'customerShippingZipCode',
            ],
            snippet: 'sw-restricted-rules.restrictedAssignment.personaPromotions',
        },
    );

    ruleConditionService.addAwarenessConfiguration(
        'orderPromotions',
        {
            notEquals: [
                'cartCartAmount',
            ],
            equalsAny: [
                'customerOrderCount',
                'customerDaysSinceLastOrder',
                'customerBillingCountry',
                'customerBillingStreet',
                'customerBillingZipCode',
                'customerCustomerGroup',
                'customerCustomerNumber',
                'customerDifferentAddresses',
                'customerIsNewCustomer',
                'customerLastName',
                'customerShippingCountry',
                'customerShippingStreet',
                'customerShippingZipCode',
            ],
            snippet: 'sw-restricted-rules.restrictedAssignment.orderPromotions',
        },
    );

    ruleConditionService.addAwarenessConfiguration(
        'cartPromotions',
        {
            notEquals: [
                'cartCartAmount',
            ],
            snippet: 'sw-restricted-rules.restrictedAssignment.cartPromotions',
        },
    );

    ruleConditionService.addAwarenessConfiguration(
        'promotionSetGroups',
        {
            notEquals: [
                'cartCartAmount',
            ],
            snippet: 'sw-restricted-rules.restrictedAssignment.promotionSetGroups',
        },
    );

    ruleConditionService.addAwarenessConfiguration(
        'promotionDiscounts',
        {
            notEquals: [
                'cartCartAmount',
            ],
            snippet: 'sw-restricted-rules.restrictedAssignment.promotionDiscounts',
        },
    );

    ruleConditionService.addAwarenessConfiguration(
        'shippingMethodPriceCalculations',
        {
            notEquals: [
                'cartCartAmount',
            ],
            snippet: 'sw-restricted-rules.restrictedAssignment.shippingMethodPriceCalculations',
        },
    );

    ruleConditionService.addAwarenessConfiguration(
        'shippingMethodPrices',
        {
            notEquals: [
                'cartCartAmount',
            ],
            snippet: 'sw-restricted-rules.restrictedAssignment.shippingMethodPrices',
        },
    );

    ruleConditionService.addCondition('dateRange', {
        component: 'sw-condition-date-range',
        label: 'global.sw-condition.condition.dateRangeRule.label',
        scopes: ['global'],
        group: 'general',
    });
    ruleConditionService.addCondition('timeRange', {
        component: 'sw-condition-time-range',
        label: 'global.sw-condition.condition.timeRangeRule',
        scopes: ['global'],
        group: 'general',
    });
    ruleConditionService.addCondition('customerOrderCount', {
        component: 'sw-condition-order-count',
        label: 'global.sw-condition.condition.orderCountRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerDaysSinceLastOrder', {
        component: 'sw-condition-days-since-last-order',
        label: 'global.sw-condition.condition.daysSinceLastOrderRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('salesChannel', {
        component: 'sw-condition-sales-channel',
        label: 'global.sw-condition.condition.salesChannelRule',
        scopes: ['global'],
        group: 'general',
    });
    ruleConditionService.addCondition('currency', {
        component: 'sw-condition-currency',
        label: 'global.sw-condition.condition.currencyRule',
        scopes: ['global'],
        group: 'general',
    });
    ruleConditionService.addCondition('language', {
        component: 'sw-condition-language',
        label: 'global.sw-condition.condition.languageRule',
        scopes: ['global'],
        group: 'general',
    });
    ruleConditionService.addCondition('cartTaxDisplay', {
        component: 'sw-condition-cart-tax-display',
        label: 'global.sw-condition.condition.cartTaxDisplay.label',
        scopes: ['checkout'],
        group: 'general',
    });
    ruleConditionService.addCondition('customerBillingCountry', {
        component: 'sw-condition-billing-country',
        label: 'global.sw-condition.condition.billingCountryRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerBillingStreet', {
        component: 'sw-condition-billing-street',
        label: 'global.sw-condition.condition.billingStreetRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerBillingZipCode', {
        component: 'sw-condition-billing-zip-code',
        label: 'global.sw-condition.condition.billingZipCodeRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerCustomerGroup', {
        component: 'sw-condition-customer-group',
        label: 'global.sw-condition.condition.customerGroupRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerTag', {
        component: 'sw-condition-customer-tag',
        label: 'global.sw-condition.condition.customerTagRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerCustomerNumber', {
        component: 'sw-condition-customer-number',
        label: 'global.sw-condition.condition.customerNumberRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerDifferentAddresses', {
        component: 'sw-condition-different-addresses',
        label: 'global.sw-condition.condition.differentAddressesRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerEmail', {
        component: 'sw-condition-email',
        label: 'global.sw-condition.condition.emailRule.label',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerIsNewCustomer', {
        component: 'sw-condition-is-new-customer',
        label: 'global.sw-condition.condition.isNewCustomerRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerLastName', {
        component: 'sw-condition-last-name',
        label: 'global.sw-condition.condition.lastNameRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerIsCompany', {
        component: 'sw-condition-is-company',
        label: 'global.sw-condition.condition.isCompanyRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerIsGuest', {
        component: 'sw-condition-is-guest',
        label: 'global.sw-condition.condition.isGuestRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerIsNewsletterRecipient', {
        component: 'sw-condition-is-newsletter-recipient',
        label: 'global.sw-condition.condition.isNewsletterRecipient',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerShippingCountry', {
        component: 'sw-condition-shipping-country',
        label: 'global.sw-condition.condition.shippingCountryRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerShippingStreet', {
        component: 'sw-condition-shipping-street',
        label: 'global.sw-condition.condition.shippingStreetRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerShippingZipCode', {
        component: 'sw-condition-shipping-zip-code',
        label: 'global.sw-condition.condition.shippingZipCodeRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerLoggedIn', {
        component: 'sw-condition-customer-logged-in',
        label: 'global.sw-condition.condition.customerLoggedInRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('cartCartAmount', {
        component: 'sw-condition-cart-amount',
        label: 'global.sw-condition.condition.cartAmountRule',
        scopes: ['cart'],
        group: 'cart',
    });
    ruleConditionService.addCondition('cartPositionPrice', {
        component: 'sw-condition-cart-position-price',
        label: 'global.sw-condition.condition.cartPositionPrice',
        scopes: ['cart'],
        group: 'cart',
    });
    ruleConditionService.addCondition('cartGoodsCount', {
        component: 'sw-condition-goods-count',
        label: 'global.sw-condition.condition.goodsCountRule',
        scopes: ['cart'],
        group: 'cart',
    });
    ruleConditionService.addCondition('cartGoodsPrice', {
        component: 'sw-condition-goods-price',
        label: 'global.sw-condition.condition.goodsPriceRule',
        scopes: ['cart'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemOfType', {
        component: 'sw-condition-line-item-of-type',
        label: 'global.sw-condition.condition.lineItemOfTypeRule.label',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItem', {
        component: 'sw-condition-line-item',
        label: 'global.sw-condition.condition.lineItemRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    /** @major-deprecated (flag:FEATURE_NEXT_17016) This rule will be removed. Use cartLineItem instead. */
    if (!Feature.isActive('FEATURE_NEXT_17016')) {
        /*
        * NOTE: When removing FEATURE_NEXT_17016 move contents of
        *   `global.sw-condition.condition.lineItemsInCartRule` in
        *   `global.sw-condition.condition.lineItemRule` and remove snippet
        *   `global.sw-condition.condition.lineItemsInCartRule`
        */
        ruleConditionService.addCondition('cartLineItemsInCart', {
            component: 'sw-condition-line-items-in-cart',
            label: Feature.isActive('FEATURE_NEXT_17016') ?
                'global.sw-condition.condition.lineItemRule' :
                'global.sw-condition.condition.lineItemsInCartRule',
            scopes: ['cart'],
            group: 'item',
        });
    }
    ruleConditionService.addCondition('cartLineItemsInCartCount', {
        component: 'sw-condition-line-items-in-cart-count',
        label: 'global.sw-condition.condition.lineItemsInCartCountRule',
        scopes: ['cart'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemTotalPrice', {
        component: 'sw-condition-line-item-total-price',
        label: 'global.sw-condition.condition.lineItemTotalPriceRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemUnitPrice', {
        component: 'sw-condition-line-item-unit-price',
        label: 'global.sw-condition.condition.lineItemUnitPriceRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemWithQuantity', {
        component: 'sw-condition-line-item-with-quantity',
        label: 'global.sw-condition.condition.lineItemWithQuantityRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartHasDeliveryFreeItem', {
        component: 'sw-condition-cart-has-delivery-free-item',
        label: 'global.sw-condition.condition.hasDeliveryFreeItemRule',
        scopes: ['cart'],
        group: 'item',
    });
    ruleConditionService.addCondition('dayOfWeek', {
        component: 'sw-condition-day-of-week',
        label: 'global.sw-condition.condition.dayOfWeekRule',
        scopes: ['global'],
        group: 'general',
    });
    ruleConditionService.addCondition('cartWeight', {
        component: 'sw-condition-weight-of-cart',
        label: 'global.sw-condition.condition.weightOfCartRule',
        scopes: ['cart'],
        group: 'cart',
    });
    ruleConditionService.addCondition('cartVolume', {
        component: 'sw-condition-volume-of-cart',
        label: 'global.sw-condition.condition.volumeOfCartRule',
        scopes: ['cart'],
        group: 'cart',
    });
    ruleConditionService.addCondition('cartLineItemTag', {
        component: 'sw-condition-line-item-tag',
        label: 'global.sw-condition.condition.lineItemTagRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('alwaysValid', {
        component: 'sw-condition-is-always-valid',
        label: 'global.sw-condition.condition.alwaysValidRule',
        scopes: ['global'],
        group: 'general',
    });
    ruleConditionService.addCondition('cartLineItemProperty', {
        component: 'sw-condition-line-item-property',
        label: 'global.sw-condition.condition.lineItemPropertyRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemIsNew', {
        component: 'sw-condition-line-item-is-new',
        label: 'global.sw-condition.condition.lineItemIsNewRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemOfManufacturer', {
        component: 'sw-condition-line-item-of-manufacturer',
        label: 'global.sw-condition.condition.lineItemOfManufacturerRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemPurchasePrice', {
        component: 'sw-condition-line-item-purchase-price',
        label: 'global.sw-condition.condition.lineItemPurchasePriceRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemCreationDate', {
        component: 'sw-condition-line-item-creation-date',
        label: 'global.sw-condition.condition.lineItemCreationDateRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemReleaseDate', {
        component: 'sw-condition-line-item-release-date',
        label: 'global.sw-condition.condition.lineItemReleaseDateRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemClearanceSale', {
        component: 'sw-condition-line-item-clearance-sale',
        label: 'global.sw-condition.condition.lineItemClearanceSale',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemPromoted', {
        component: 'sw-condition-line-item-promoted',
        label: 'global.sw-condition.condition.lineItemPromotedRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemInCategory', {
        component: 'sw-condition-line-item-in-category',
        label: 'global.sw-condition.condition.lineItemInCategoryRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemInProductStream', {
        component: 'sw-condition-line-item-in-product-stream',
        label: 'global.sw-condition.condition.lineItemInProductStreamRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemTaxation', {
        component: 'sw-condition-line-item-taxation',
        label: 'global.sw-condition.condition.lineItemTaxationRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemDimensionWidth', {
        component: 'sw-condition-line-item-dimension-width',
        label: 'global.sw-condition.condition.lineItemDimensionWidthRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemDimensionHeight', {
        component: 'sw-condition-line-item-dimension-height',
        label: 'global.sw-condition.condition.lineItemDimensionHeightRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemDimensionLength', {
        component: 'sw-condition-line-item-dimension-length',
        label: 'global.sw-condition.condition.lineItemDimensionLengthRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemDimensionWeight', {
        component: 'sw-condition-line-item-dimension-weight',
        label: 'global.sw-condition.condition.lineItemDimensionWeightRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemDimensionVolume', {
        component: 'sw-condition-line-item-dimension-volume',
        label: 'global.sw-condition.condition.lineItemDimensionVolumeRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemOfManufacturer', {
        component: 'sw-condition-line-item-of-manufacturer',
        label: 'global.sw-condition.condition.lineItemOfManufacturerRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemPurchasePrice', {
        component: 'sw-condition-line-item-purchase-price',
        label: 'global.sw-condition.condition.lineItemPurchasePriceRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemCreationDate', {
        component: 'sw-condition-line-item-creation-date',
        label: 'global.sw-condition.condition.lineItemCreationDateRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemListPrice', {
        component: 'sw-condition-line-item-list-price',
        label: 'global.sw-condition.condition.lineItemListPriceRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemListPriceRatio', {
        component: 'sw-condition-line-item-list-price-ratio',
        label: 'global.sw-condition.condition.lineItemListPriceRatioRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemCustomField', {
        component: 'sw-condition-line-item-custom-field',
        label: 'global.sw-condition.condition.lineItemCustomFieldRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemStock', {
        component: 'sw-condition-line-item-stock',
        label: 'global.sw-condition.condition.lineItemStockRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemActualStock', {
        component: 'sw-condition-line-item-actual-stock',
        label: 'global.sw-condition.condition.lineItemActualStockRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('customerCustomField', {
        component: 'sw-condition-customer-custom-field',
        label: 'global.sw-condition.condition.customerCustomFieldRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('paymentMethod', {
        component: 'sw-condition-payment-method',
        label: 'global.sw-condition.condition.paymentMethodRule',
        scopes: ['cart'],
        group: 'cart',
    });

    ruleConditionService.addCondition('shippingMethod', {
        component: 'sw-condition-shipping-method',
        label: 'global.sw-condition.condition.shippingMethodRule',
        scopes: ['cart'],
        group: 'cart',
    });

    ruleConditionService.addCondition('cartLineItemGoodsTotal', {
        component: 'sw-condition-line-item-goods-total',
        label: 'global.sw-condition.condition.lineItemGoodsTotalRule',
        scopes: ['lineItem'],
        group: 'cart',
    });

    ruleConditionService.addCondition('customerOrderTotalAmount', {
        component: 'sw-condition-order-total-amount',
        label: 'global.sw-condition.condition.orderTotalAmountRule',
        scopes: ['checkout'],
        group: 'customer',
    });

    ruleConditionService.addCondition('promotionLineItem', {
        component: 'sw-condition-promotion-line-item',
        label: 'global.sw-condition.condition.promotionLineItemRule',
        scopes: ['cart'],
        group: 'promotion',
    });

    ruleConditionService.addCondition('promotionCodeOfType', {
        component: 'sw-condition-promotion-code-of-type',
        label: 'global.sw-condition.condition.promotionCodeOfType',
        scopes: ['cart'],
        group: 'promotion',
    });

    ruleConditionService.addCondition('promotionsInCartCount', {
        component: 'sw-condition-promotions-in-cart-count',
        label: 'global.sw-condition.condition.promotionsInCartCountRule',
        scopes: ['cart'],
        group: 'promotion',
    });

    ruleConditionService.addCondition('promotionValue', {
        component: 'sw-condition-promotion-value',
        label: 'global.sw-condition.condition.promotionValueRule',
        scopes: ['cart'],
        group: 'promotion',
    });

    return ruleConditionService;
});
