import type RuleConditionService from '../service/rule-condition.service';

const { Application } = Shopware;

/**
 * @sw-package fundamentals@after-sales
 */
Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService: RuleConditionService) => {
    ruleConditionService.addCondition('dateRange', {
        component: 'sw-condition-date-range',
        scopes: ['global'],
        group: 'general',
        snippets: {
            label: 'global.sw-condition.condition.dateRangeRule.label',
            fields: {
                withTime: {
                    placeholder: 'global.sw-condition.condition.withTime',
                },
                fromDate: {
                    placeholder: 'sw-datepicker.date.placeholder',
                },
                toDate: {
                    placeholder: 'sw-datepicker.date.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('timeRange', {
        component: 'sw-condition-time-range',
        scopes: ['global'],
        group: 'general',
        snippets: {
            label: 'global.sw-condition.condition.timeRangeRule',
            fields: {
                fromTime: {
                    placeholder: 'sw-datepicker.time.placeholder',
                },
                toTime: {
                    placeholder: 'sw-datepicker.time.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('numberOfReviews', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.numberOfReviews',
        },
    });

    ruleConditionService.addCondition('customerOrderCount', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.orderCountRule',
        },
    });

    ruleConditionService.addCondition('customerDaysSinceLastOrder', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.daysSinceLastOrderRule',
            fields: {
                daysPassed: {
                    placeholder: 'sw-product.settingsForm.placeholderTime',
                },
            },
        },
    });

    ruleConditionService.addCondition('salesChannel', {
        component: 'sw-condition-generic',
        scopes: ['global'],
        group: 'general',
        snippets: {
            label: 'global.sw-condition.condition.salesChannelRule',
            fields: {
                salesChannelIds: {
                    placeholder: 'sw-order.createBase.placeholderSalesChannel',
                },
            },
        },
    });

    ruleConditionService.addCondition('currency', {
        component: 'sw-condition-generic',
        scopes: ['global'],
        group: 'general',
        snippets: {
            label: 'global.sw-condition.condition.currencyRule',
        },
    });

    ruleConditionService.addCondition('language', {
        component: 'sw-condition-generic',
        scopes: ['global'],
        group: 'general',
        snippets: {
            label: 'global.sw-condition.condition.languageRule',
        },
    });

    ruleConditionService.addCondition('cartTaxDisplay', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'general',
        snippets: {
            label: 'global.sw-condition.condition.cartTaxDisplay.label',
            fields: {
                taxDisplay: {
                    options: {
                        gross: 'global.sw-condition.condition.cartTaxDisplay.gross',
                        net: 'global.sw-condition.condition.cartTaxDisplay.net',
                    },
                },
            },
        },
    });

    ruleConditionService.addCondition('customerBillingCountry', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.billingCountryRule',
        },
    });

    ruleConditionService.addCondition('customerBillingStreet', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.billingStreetRule',
        },
    });

    ruleConditionService.addCondition('customerBillingZipCode', {
        component: 'sw-condition-billing-zip-code',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.billingZipCodeRule',
            fields: {
                alphanumericZipCodes: {
                    placeholder: [
                        'global.sw-tagged-field.text-default-placeholder',
                        ' ',
                        'global.sw-condition.condition.zipCodeWildcardPlaceholder',
                    ],
                },
            },
        },
    });

    ruleConditionService.addCondition('customerCustomerGroup', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerGroupRule',
        },
    });

    ruleConditionService.addCondition('customerRequestedGroup', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerRequestedGroupRule',
        },
    });

    ruleConditionService.addCondition('customerTag', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerTagRule',
        },
    });

    ruleConditionService.addCondition('customerCustomerNumber', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerNumberRule',
        },
    });

    ruleConditionService.addCondition('customerDifferentAddresses', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.differentAddressesRule',
        },
    });

    ruleConditionService.addCondition('customerEmail', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.emailRule.label',
            fields: {
                email: {
                    placeholder: 'global.sw-condition.condition.emailRule.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('customerLastName', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.lastNameRule',
        },
    });

    ruleConditionService.addCondition('customerIsCompany', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.isCompanyRule',
        },
    });

    ruleConditionService.addCondition('customerIsGuest', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.isGuestRule',
        },
    });

    ruleConditionService.addCondition('customerIsNewsletterRecipient', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.isNewsletterRecipient',
        },
    });

    ruleConditionService.addCondition('customerShippingCountry', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.shippingCountryRule',
        },
    });

    ruleConditionService.addCondition('customerShippingStreet', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.shippingStreetRule',
        },
    });

    ruleConditionService.addCondition('customerShippingZipCode', {
        component: 'sw-condition-shipping-zip-code',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.shippingZipCodeRule',
            fields: {
                alphanumericZipCodes: {
                    placeholder: [
                        'global.sw-tagged-field.text-default-placeholder',
                        ' ',
                        'global.sw-condition.condition.zipCodeWildcardPlaceholder',
                    ],
                },
            },
        },
    });

    ruleConditionService.addCondition('customerLoggedIn', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerLoggedInRule',
        },
    });

    ruleConditionService.addCondition('customerBillingCity', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.billingCityRule',
        },
    });

    ruleConditionService.addCondition('customerBillingState', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.billingStateRule',
        },
    });

    ruleConditionService.addCondition('customerIsActive', {
        component: 'sw-condition-generic',
        scopes: ['global'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerIsActiveRule',
        },
    });

    ruleConditionService.addCondition('customerShippingCity', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.shippingCityRule',
        },
    });

    ruleConditionService.addCondition('customerShippingState', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.shippingStateRule',
        },
    });

    ruleConditionService.addCondition('customerAge', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerAgeRule',
        },
    });

    ruleConditionService.addCondition('customerDaysSinceLastLogin', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerDaysSinceLastLogin',
            fields: {
                daysPassed: {
                    placeholder: 'sw-product.settingsForm.placeholderTime',
                },
            },
        },
    });

    ruleConditionService.addCondition('customerDaysSinceFirstLogin', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerDaysSinceFirstLogin',
            fields: {
                daysPassed: {
                    placeholder: 'sw-product.settingsForm.placeholderTime',
                },
            },
        },
    });

    ruleConditionService.addCondition('customerAffiliateCode', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerAffiliateCodeRule',
        },
    });

    ruleConditionService.addCondition('customerCampaignCode', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerCampaignCodeRule',
        },
    });

    ruleConditionService.addCondition('orderAffiliateCode', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.orderAffiliateCodeRule',
        },
    });

    ruleConditionService.addCondition('orderCampaignCode', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.orderCampaignCodeRule',
        },
    });

    ruleConditionService.addCondition('cartCartAmount', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'cart',
        snippets: {
            label: 'global.sw-condition.condition.cartAmountRule',
        },
    });

    ruleConditionService.addCondition('cartPositionPrice', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'cart',
        snippets: {
            label: 'global.sw-condition.condition.cartPositionPrice',
        },
    });

    ruleConditionService.addCondition('cartGoodsCount', {
        component: 'sw-condition-goods-count',
        scopes: ['cart'],
        group: 'cart',
        snippets: {
            label: 'global.sw-condition.condition.goodsCountRule',
        },
    });

    ruleConditionService.addCondition('cartTotalPurchasePrice', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'cart',
        snippets: {
            label: 'global.sw-condition.condition.cartTotalPurchasePrice',
            fields: {
                type: {
                    options: {
                        gross: 'global.sw-condition.operator.gross',
                        net: 'global.sw-condition.operator.net',
                    },
                    placeholder: 'global.sw-condition.operator.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('cartGoodsPrice', {
        component: 'sw-condition-goods-price',
        scopes: ['cart'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.goodsPriceRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemOfType', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemOfTypeRule.label',
            fields: {
                lineItemType: {
                    options: {
                        product: 'global.sw-condition.condition.lineItemOfTypeRule.product',
                        promotion: 'global.sw-condition.condition.lineItemOfTypeRule.discount_surcharge',
                    },
                },
            },
        },
    });

    ruleConditionService.addCondition('cartLineItem', {
        component: 'sw-condition-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemsInCartCount', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemsInCartCountRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemTotalPrice', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemTotalPriceRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemUnitPrice', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemUnitPriceRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemWithQuantity', {
        component: 'sw-condition-line-item-with-quantity',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemWithQuantityRule',
        },
    });

    ruleConditionService.addCondition('cartHasDeliveryFreeItem', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.hasDeliveryFreeItemRule',
        },
    });

    ruleConditionService.addCondition('dayOfWeek', {
        component: 'sw-condition-generic',
        scopes: ['global'],
        group: 'general',
        snippets: {
            label: 'global.sw-condition.condition.dayOfWeekRule',
            fields: {
                dayOfWeek: {
                    options: {
                        '1': 'global.day-of-week.monday',
                        '2': 'global.day-of-week.tuesday',
                        '3': 'global.day-of-week.wednesday',
                        '4': 'global.day-of-week.thursday',
                        '5': 'global.day-of-week.friday',
                        '6': 'global.day-of-week.saturday',
                        '7': 'global.day-of-week.sunday',
                    },
                },
            },
        },
    });

    ruleConditionService.addCondition('cartWeight', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'cart',
        snippets: {
            label: 'global.sw-condition.condition.weightOfCartRule',
        },
    });

    ruleConditionService.addCondition('cartVolume', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'cart',
        snippets: {
            label: 'global.sw-condition.condition.volumeOfCartRule',
        },
    });

    ruleConditionService.addCondition('cartShippingCost', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'cart',
        snippets: {
            label: 'global.sw-condition.condition.cartShippingCost',
        },
    });

    ruleConditionService.addCondition('cartLineItemTag', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemTagRule',
        },
    });

    ruleConditionService.addCondition('alwaysValid', {
        component: 'sw-condition-is-always-valid',
        scopes: ['global'],
        group: 'general',
        snippets: {
            label: 'global.sw-condition.condition.alwaysValidRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemProperty', {
        component: 'sw-condition-line-item-property',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemPropertyRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemIsNew', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemIsNewRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemOfManufacturer', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemOfManufacturerRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemPurchasePrice', {
        component: 'sw-condition-line-item-purchase-price',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemPurchasePriceRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemCreationDate', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemCreationDateRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemReleaseDate', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemReleaseDateRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemClearanceSale', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemClearanceSale',
        },
    });

    ruleConditionService.addCondition('cartLineItemPromoted', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemPromotedRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemInCategory', {
        component: 'sw-condition-line-item-in-category',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemInCategoryRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemInProductStream', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemInProductStreamRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemTaxation', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemTaxationRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemDimensionWidth', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemDimensionWidthRule',
            fields: {
                amount: {
                    placeholder: 'sw-product.settingsForm.placeholderWidth',
                },
            },
        },
    });

    ruleConditionService.addCondition('cartLineItemDimensionHeight', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemDimensionHeightRule',
            fields: {
                amount: {
                    placeholder: 'sw-product.settingsForm.placeholderHeight',
                },
            },
        },
    });

    ruleConditionService.addCondition('cartLineItemDimensionLength', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemDimensionLengthRule',
            fields: {
                amount: {
                    placeholder: 'sw-product.settingsForm.placeholderLength',
                },
            },
        },
    });

    ruleConditionService.addCondition('cartLineItemDimensionWeight', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemDimensionWeightRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemDimensionVolume', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemDimensionVolumeRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemListPrice', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemListPriceRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemListPriceRatio', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemListPriceRatioRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemCustomField', {
        component: 'sw-condition-line-item-custom-field',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemCustomFieldRule',
            fields: {
                customField: {
                    placeholder: 'global.sw-condition.condition.lineItemCustomField.field.customFieldSelect.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('cartLineItemStock', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemStockRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemActualStock', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemActualStockRule',
        },
    });

    ruleConditionService.addCondition('customerCustomField', {
        component: 'sw-condition-customer-custom-field',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerCustomFieldRule',
            fields: {
                customField: {
                    placeholder: 'global.sw-condition.condition.lineItemCustomField.field.customFieldSelect.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('paymentMethod', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'cart',
        snippets: {
            label: 'global.sw-condition.condition.paymentMethodRule',
        },
    });

    ruleConditionService.addCondition('shippingMethod', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'cart',
        snippets: {
            label: 'global.sw-condition.condition.shippingMethodRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemGoodsTotal', {
        component: 'sw-condition-line-item-goods-total',
        scopes: ['lineItem'],
        group: 'cart',
        snippets: {
            label: 'global.sw-condition.condition.lineItemGoodsTotalRule',
        },
    });

    ruleConditionService.addCondition('customerOrderTotalAmount', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.orderTotalAmountRule',
        },
    });

    ruleConditionService.addCondition('promotionLineItem', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'promotion',
        snippets: {
            label: 'global.sw-condition.condition.promotionLineItemRule',
        },
    });

    ruleConditionService.addCondition('promotionCodeOfType', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'promotion',
        snippets: {
            label: 'global.sw-condition.condition.promotionCodeOfType',
            fields: {
                promotionCodeType: {
                    options: {
                        global: 'global.sw-condition.condition.promotionCodeOfTypeRule.global',
                        fixed: 'global.sw-condition.condition.promotionCodeOfTypeRule.fixed',
                        individual: 'global.sw-condition.condition.promotionCodeOfTypeRule.individual',
                    },
                },
            },
        },
    });

    ruleConditionService.addCondition('promotionsInCartCount', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'promotion',
        snippets: {
            label: 'global.sw-condition.condition.promotionsInCartCountRule',
        },
    });

    ruleConditionService.addCondition('promotionValue', {
        component: 'sw-condition-generic',
        scopes: ['cart'],
        group: 'promotion',
        snippets: {
            label: 'global.sw-condition.condition.promotionValueRule',
        },
    });

    ruleConditionService.addCondition('customerBirthday', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerBirthdayRule',
            fields: {
                birthday: {
                    placeholder: 'sw-datepicker.datetime.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('customerCreatedByAdmin', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerCreatedByAdminRule',
        },
    });

    ruleConditionService.addCondition('customerSalutation', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'customer',
        snippets: {
            label: 'global.sw-condition.condition.customerSalutationRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemProductStates', {
        component: 'sw-condition-generic-line-item',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemProductStates',
            fields: {
                productState: {
                    options: {
                        'is-physical': 'sw-product.filters.productStatesFilter.options.physical',
                        'is-download': 'sw-product.filters.productStatesFilter.options.digital',
                    },
                },
            },
        },
    });

    ruleConditionService.addCondition('orderTag', {
        component: 'sw-condition-generic',
        scopes: ['order'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.orderTagRule',
        },
    });

    ruleConditionService.addCondition('orderTrackingCode', {
        component: 'sw-condition-generic',
        scopes: ['order'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.orderTrackingCodeRule',
        },
    });

    ruleConditionService.addCondition('orderDeliveryStatus', {
        component: 'sw-condition-generic',
        scopes: ['order'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.orderDeliveryStatusRule',
        },
    });

    ruleConditionService.addCondition('adminSalesChannelSource', {
        component: 'sw-condition-generic',
        scopes: ['checkout'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.adminSalesChannelSourceRule',
            fields: {
                hasAdminSalesChannelSource: {
                    placeholder: 'sw-settings-rule.filter.conditionFilter.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('orderTransactionStatus', {
        component: 'sw-condition-generic',
        scopes: ['order'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.orderTransactionStatusRule',
            fields: {
                stateIds: {
                    placeholder: '',
                },
                operator: {
                    placeholder: 'global.sw-condition.operator.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('orderStatus', {
        component: 'sw-condition-generic',
        scopes: ['order'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.orderStatusRule',
            fields: {
                stateIds: {
                    placeholder: '',
                },
                operator: {
                    placeholder: 'global.sw-condition.operator.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('orderCreatedByAdmin', {
        component: 'sw-condition-generic',
        scopes: ['order'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.orderCreatedByAdminRule',
            fields: {
                shouldOrderBeCreatedByAdmin: {
                    placeholder: 'sw-settings-rule.filter.conditionFilter.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('orderCustomField', {
        component: 'sw-condition-order-custom-field',
        scopes: ['order'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.orderCustomFieldRule',
            fields: {
                customField: {
                    placeholder: 'global.sw-condition.condition.lineItemCustomField.field.customFieldSelect.placeholder',
                },
            },
        },
    });

    ruleConditionService.addCondition('orderDocumentType', {
        component: 'sw-condition-generic',
        scopes: ['order'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.orderDocumentTypeRule',
        },
    });

    ruleConditionService.addCondition('orderDocumentTypeSent', {
        component: 'sw-condition-generic',
        scopes: ['order'],
        group: 'order',
        snippets: {
            label: 'global.sw-condition.condition.orderDocumentTypeSentRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemPropertyValue', {
        component: 'sw-condition-line-item-property',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemPropertyValueRule',
        },
    });

    ruleConditionService.addCondition('cartLineItemVariantValue', {
        component: 'sw-condition-line-item-property',
        scopes: ['lineItem'],
        group: 'item',
        snippets: {
            label: 'global.sw-condition.condition.lineItemVariantValueRule',
        },
    });

    ruleConditionService.addAwarenessConfiguration('personaPromotions', {
        notEquals: [
            'cartCartAmount',
            'cartShippingCost',
        ],
        equalsAny: [
            ...ruleConditionService.getRestrictionsByGroup('customer'),
            'alwaysValid',
        ],
        snippet: 'sw-restricted-rules.restrictedAssignment.personaPromotions',
    });

    ruleConditionService.addAwarenessConfiguration('orderPromotions', {
        notEquals: [
            'cartCartAmount',
            'cartShippingCost',
        ],
        snippet: 'sw-restricted-rules.restrictedAssignment.orderPromotions',
    });

    ruleConditionService.addAwarenessConfiguration('cartPromotions', {
        notEquals: [
            'cartCartAmount',
            'cartShippingCost',
        ],
        snippet: 'sw-restricted-rules.restrictedAssignment.cartPromotions',
    });

    ruleConditionService.addAwarenessConfiguration('promotionSetGroups', {
        notEquals: [
            'cartCartAmount',
            'cartShippingCost',
        ],
        snippet: 'sw-restricted-rules.restrictedAssignment.promotionSetGroups',
    });

    ruleConditionService.addAwarenessConfiguration('promotionDiscounts', {
        notEquals: [
            'cartCartAmount',
            'cartShippingCost',
        ],
        snippet: 'sw-restricted-rules.restrictedAssignment.promotionDiscounts',
    });

    ruleConditionService.addAwarenessConfiguration('shippingMethodPriceCalculations', {
        notEquals: [
            'cartCartAmount',
            'cartShippingCost',
        ],
        snippet: 'sw-restricted-rules.restrictedAssignment.shippingMethodPriceCalculations',
    });

    ruleConditionService.addAwarenessConfiguration('shippingMethodPrices', {
        notEquals: [
            'cartCartAmount',
            'cartShippingCost',
        ],
        snippet: 'sw-restricted-rules.restrictedAssignment.shippingMethodPrices',
    });

    return ruleConditionService;
});
