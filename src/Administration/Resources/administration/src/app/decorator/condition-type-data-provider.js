import { Application } from 'src/core/shopware';

Application.addServiceProviderDecorator('ruleConditionService', (ruleConditionService) => {
    ruleConditionService.addCondition('Shopware\\Core\\Framework\\Rule\\DateRangeRule', {
        label: 'global.sw-condition-group.condition.dateRangeRule.label',
        fields: {
            fromDate: {
                mode: 'static',
                type: 'datetime'
            },
            toDate: {
                mode: 'static',
                type: 'datetime'
            },
            useTime: {
                mode: 'static',
                label: 'global.sw-condition-group.condition.dateRangeRule.useTime',
                type: 'select',
                values: {
                    true: {
                        label: 'global.sw-condition-group.condition.yes'
                    },
                    false: {
                        label: 'global.sw-condition-group.condition.no'
                    }
                }
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Framework\\Rule\\SalesChannelRule', {
        label: 'global.sw-condition-group.condition.salesChannelRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.multiStore
            },
            salesChannelIds: {
                mode: 'store',
                type: 'multiselect',
                store: 'sales_channel'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Framework\\Rule\\CurrencyRule', {
        label: 'global.sw-condition-group.condition.currencyRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.multiStore
            },
            currencyIds: {
                mode: 'store',
                type: 'multiselect',
                store: 'currency'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\BillingCountryRule', {
        label: 'global.sw-condition-group.condition.billingCountryRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.multiStore
            },
            countryIds: {
                mode: 'store',
                type: 'multiselect',
                store: 'country'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\BillingStreetRule', {
        label: 'global.sw-condition-group.condition.billingStreetRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.string
            },
            streetName: {
                mode: 'free',
                type: 'text'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\BillingZipCodeRule', {
        label: 'global.sw-condition-group.condition.billingZipCodeRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.multiStore
            },
            zipCodes: {
                mode: 'free',
                type: 'tags'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\CustomerGroupRule', {
        label: 'global.sw-condition-group.condition.customerGroupRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.multiStore
            },
            customerGroupIds: {
                mode: 'store',
                type: 'multiselect',
                store: 'customer_group'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\CustomerNumberRule', {
        label: 'global.sw-condition-group.condition.customerNumberRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.multiStore
            },
            customerNumbers: {
                mode: 'free',
                type: 'tags'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\DifferentAddressRule', {
        label: 'global.sw-condition-group.condition.differentAddressRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.bool
            },
            value: {
                mode: 'static',
                type: 'select',
                values: {
                    true: {
                        label: 'global.sw-condition-group.condition.yes'
                    },
                    false: {
                        label: 'global.sw-condition-group.condition.no'
                    }
                }
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\IsNewCustomerRule', {
        label: 'global.sw-condition-group.condition.isNewCustomerRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.bool
            },
            value: {
                mode: 'static',
                type: 'select',
                values: {
                    true: {
                        label: 'global.sw-condition-group.condition.yes'
                    },
                    false: {
                        label: 'global.sw-condition-group.condition.no'
                    }
                }
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\LastNameRule', {
        label: 'global.sw-condition-group.condition.lastNameRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.string
            },
            lastName: {
                mode: 'free',
                type: 'text'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\ShippingCountryRule', {
        label: 'global.sw-condition-group.condition.shippingCountryRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.multiStore
            },
            countryIds: {
                mode: 'store',
                type: 'multiselect',
                store: 'country'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\ShippingStreetRule', {
        label: 'global.sw-condition-group.condition.shippingStreetRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.string
            },
            streetName: {
                mode: 'free',
                type: 'text'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Customer\\Rule\\ShippingZipCodeRule', {
        label: 'global.sw-condition-group.condition.shippingZipCodeRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.multiStore
            },
            zipCodes: {
                mode: 'free',
                type: 'tags'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\CartAmountRule', {
        label: 'global.sw-condition-group.condition.cartAmountRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.number
            },
            amount: {
                mode: 'free',
                type: 'decimal'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\GoodsCountRule', {
        label: 'global.sw-condition-group.condition.goodsCountRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.number
            },
            count: {
                mode: 'free',
                type: 'int'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\GoodsPriceRule', {
        label: 'global.sw-condition-group.condition.goodsPriceRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.number
            },
            amount: {
                mode: 'free',
                type: 'decimal'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemOfTypeRule', {
        label: 'global.sw-condition-group.condition.lineItemOfTypeRule.label',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.string
            },
            lineItemType: {
                mode: 'static',
                type: 'select',
                values: { /* todo add possible line item types */
                    product: {
                        label: 'global.sw-condition-group.condition.lineItemOfTypeRule.product'
                    }
                }
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemRule', {
        label: 'global.sw-condition-group.condition.lineItemRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.multiStore
            },
            identifiers: {
                mode: 'store',
                type: 'multiselect',
                store: 'product' // todo add correct store
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemsInCartRule', {
        label: 'global.sw-condition-group.condition.lineItemsInCartRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.multiStore
            },
            identifiers: {
                mode: 'store',
                type: 'multiselect',
                store: 'product' // todo add correct store
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemTotalPriceRule', {
        label: 'global.sw-condition-group.condition.lineItemTotalPriceRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.number
            },
            amount: {
                mode: 'free',
                type: 'decimal'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemUnitPriceRule', {
        label: 'global.sw-condition-group.condition.lineItemUnitPriceRule',
        fields: {
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.number
            },
            amount: {
                mode: 'free',
                type: 'decimal'
            }
        }
    });
    ruleConditionService.addCondition('Shopware\\Core\\Checkout\\Cart\\Rule\\LineItemWithQuantityRule', {
        label: 'global.sw-condition-group.condition.lineItemWithQuantityRule',
        fields: {
            id: {
                mode: 'store',
                type: 'select',
                store: 'product' // todo add correct store
            },
            operator: {
                mode: 'static',
                type: 'select',
                values: ruleConditionService.operatorSets.number
            },
            quantity: {
                mode: 'free',
                type: 'int'
            }
        }
    });
    return ruleConditionService;
});
