const { Application, Feature } = Shopware;
const isMajorFlagActive = Feature.isActive('v6.5.0.0');

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
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-order-count',
        label: 'global.sw-condition.condition.orderCountRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerDaysSinceLastOrder', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-days-since-last-order',
        label: 'global.sw-condition.condition.daysSinceLastOrderRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('salesChannel', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-sales-channel',
        label: 'global.sw-condition.condition.salesChannelRule',
        scopes: ['global'],
        group: 'general',
    });
    ruleConditionService.addCondition('currency', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-currency',
        label: 'global.sw-condition.condition.currencyRule',
        scopes: ['global'],
        group: 'general',
    });
    ruleConditionService.addCondition('language', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-language',
        label: 'global.sw-condition.condition.languageRule',
        scopes: ['global'],
        group: 'general',
    });
    ruleConditionService.addCondition('cartTaxDisplay', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-cart-tax-display',
        label: 'global.sw-condition.condition.cartTaxDisplay.label',
        scopes: ['checkout'],
        group: 'general',
    });
    ruleConditionService.addCondition('customerBillingCountry', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-billing-country',
        label: 'global.sw-condition.condition.billingCountryRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerBillingStreet', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-billing-street',
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
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-customer-group',
        label: 'global.sw-condition.condition.customerGroupRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerTag', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-customer-tag',
        label: 'global.sw-condition.condition.customerTagRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerCustomerNumber', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-customer-number',
        label: 'global.sw-condition.condition.customerNumberRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerDifferentAddresses', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-different-addresses',
        label: 'global.sw-condition.condition.differentAddressesRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerEmail', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-email',
        label: 'global.sw-condition.condition.emailRule.label',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerIsNewCustomer', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-is-new-customer',
        label: 'global.sw-condition.condition.isNewCustomerRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerLastName', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-last-name',
        label: 'global.sw-condition.condition.lastNameRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerIsCompany', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-is-company',
        label: 'global.sw-condition.condition.isCompanyRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerIsGuest', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-is-guest',
        label: 'global.sw-condition.condition.isGuestRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerIsNewsletterRecipient', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-is-newsletter-recipient',
        label: 'global.sw-condition.condition.isNewsletterRecipient',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerShippingCountry', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-shipping-country',
        label: 'global.sw-condition.condition.shippingCountryRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('customerShippingStreet', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-shipping-street',
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
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-customer-logged-in',
        label: 'global.sw-condition.condition.customerLoggedInRule',
        scopes: ['checkout'],
        group: 'customer',
    });
    ruleConditionService.addCondition('cartCartAmount', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-cart-amount',
        label: 'global.sw-condition.condition.cartAmountRule',
        scopes: ['cart'],
        group: 'cart',
    });
    ruleConditionService.addCondition('cartPositionPrice', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-cart-position-price',
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
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-of-type',
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
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-line-items-in-cart-count',
        label: 'global.sw-condition.condition.lineItemsInCartCountRule',
        scopes: ['cart'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemTotalPrice', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-total-price',
        label: 'global.sw-condition.condition.lineItemTotalPriceRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemUnitPrice', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-unit-price',
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
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-cart-has-delivery-free-item',
        label: 'global.sw-condition.condition.hasDeliveryFreeItemRule',
        scopes: ['cart'],
        group: 'item',
    });
    ruleConditionService.addCondition('dayOfWeek', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-day-of-week',
        label: 'global.sw-condition.condition.dayOfWeekRule',
        scopes: ['global'],
        group: 'general',
    });
    ruleConditionService.addCondition('cartWeight', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-weight-of-cart',
        label: 'global.sw-condition.condition.weightOfCartRule',
        scopes: ['cart'],
        group: 'cart',
    });
    ruleConditionService.addCondition('cartVolume', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-volume-of-cart',
        label: 'global.sw-condition.condition.volumeOfCartRule',
        scopes: ['cart'],
        group: 'cart',
    });
    ruleConditionService.addCondition('cartLineItemTag', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-tag',
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
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-is-new',
        label: 'global.sw-condition.condition.lineItemIsNewRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemOfManufacturer', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-of-manufacturer',
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
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-creation-date',
        label: 'global.sw-condition.condition.lineItemCreationDateRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemReleaseDate', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-release-date',
        label: 'global.sw-condition.condition.lineItemReleaseDateRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemClearanceSale', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-clearance-sale',
        label: 'global.sw-condition.condition.lineItemClearanceSale',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemPromoted', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-promoted',
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
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-in-product-stream',
        label: 'global.sw-condition.condition.lineItemInProductStreamRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemTaxation', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-taxation',
        label: 'global.sw-condition.condition.lineItemTaxationRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemDimensionWidth', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-dimension-width',
        label: 'global.sw-condition.condition.lineItemDimensionWidthRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemDimensionHeight', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-dimension-height',
        label: 'global.sw-condition.condition.lineItemDimensionHeightRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemDimensionLength', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-dimension-length',
        label: 'global.sw-condition.condition.lineItemDimensionLengthRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemDimensionWeight', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-dimension-weight',
        label: 'global.sw-condition.condition.lineItemDimensionWeightRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemDimensionVolume', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-dimension-volume',
        label: 'global.sw-condition.condition.lineItemDimensionVolumeRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemListPrice', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-list-price',
        label: 'global.sw-condition.condition.lineItemListPriceRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemListPriceRatio', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-list-price-ratio',
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
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-stock',
        label: 'global.sw-condition.condition.lineItemStockRule',
        scopes: ['lineItem'],
        group: 'item',
    });
    ruleConditionService.addCondition('cartLineItemActualStock', {
        component: isMajorFlagActive ? 'sw-condition-generic-line-item' : 'sw-condition-line-item-actual-stock',
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
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-payment-method',
        label: 'global.sw-condition.condition.paymentMethodRule',
        scopes: ['cart'],
        group: 'cart',
    });

    ruleConditionService.addCondition('shippingMethod', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-shipping-method',
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
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-order-total-amount',
        label: 'global.sw-condition.condition.orderTotalAmountRule',
        scopes: ['checkout'],
        group: 'customer',
    });

    ruleConditionService.addCondition('promotionLineItem', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-promotion-line-item',
        label: 'global.sw-condition.condition.promotionLineItemRule',
        scopes: ['cart'],
        group: 'promotion',
    });

    ruleConditionService.addCondition('promotionCodeOfType', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-promotion-code-of-type',
        label: 'global.sw-condition.condition.promotionCodeOfType',
        scopes: ['cart'],
        group: 'promotion',
    });

    ruleConditionService.addCondition('promotionsInCartCount', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-promotions-in-cart-count',
        label: 'global.sw-condition.condition.promotionsInCartCountRule',
        scopes: ['cart'],
        group: 'promotion',
    });

    ruleConditionService.addCondition('promotionValue', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-promotion-value',
        label: 'global.sw-condition.condition.promotionValueRule',
        scopes: ['cart'],
        group: 'promotion',
    });

    ruleConditionService.addCondition('customerBirthday', {
        component: isMajorFlagActive ? 'sw-condition-generic' : 'sw-condition-customer-birthday',
        label: 'global.sw-condition.condition.customerBirthdayRule',
        scopes: ['checkout'],
        group: 'customer',
    });

    return ruleConditionService;
});
