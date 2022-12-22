const { Context } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @module app/service/rule-assignment-configuration
 */

/**
 * @private
 * @package business-ops
 * @memberOf module:core/service/rule-assignment-configuration
 * @constructor
 * @method createRuleAssignmentConfigService
 * @returns {Object}
 */
export default function createRuleAssignmentConfigService(ruleId, associationLimit) {
    const configuration = {
        product: {
            id: 'product',
            associationName: 'productPrices',
            notAssignedDataTotal: 0,
            allowAdd: false,
            entityName: 'product',
            label: 'sw-settings-rule.detail.associations.products',
            criteria: () => {
                const criteria = new Criteria(1, associationLimit);
                criteria.addFilter(Criteria.equals('prices.rule.id', ruleId));
                criteria.addAssociation('options.group');

                return criteria;
            },
            api: () => {
                const api = Object.assign({}, Context.api);
                api.inheritance = true;

                return api;
            },
            detailRoute: 'sw.product.detail.prices',
            gridColumns: [
                {
                    property: 'name',
                    label: 'Name',
                    rawData: true,
                    sortable: true,
                    routerLink: 'sw.product.detail.prices',
                    allowEdit: false,
                },
            ],
        },
        shipping_method_availability_rule: {
            id: 'shipping_method_availability_rule',
            associationName: 'shippingMethods',
            notAssignedDataTotal: 0,
            allowAdd: true,
            entityName: 'shipping_method',
            label: 'sw-settings-rule.detail.associations.shippingMethodAvailabilityRule',
            criteria: () => {
                const criteria = new Criteria(1, associationLimit);
                criteria.addFilter(Criteria.equals('availabilityRuleId', ruleId));

                return criteria;
            },
            detailRoute: 'sw.settings.shipping.detail',
            gridColumns: [
                {
                    property: 'name',
                    label: 'Name',
                    rawData: true,
                    sortable: true,
                    routerLink: 'sw.settings.shipping.detail',
                    allowEdit: false,
                },
            ],
            addContext: {
                type: 'one-to-many',
                entity: 'shipping_method',
                column: 'availabilityRuleId',
                searchColumn: 'name',
                criteria: () => {
                    const criteria = new Criteria(1, 25);
                    criteria.addFilter(Criteria.not(
                        'AND',
                        [Criteria.equals('availabilityRuleId', ruleId)],
                    ));

                    return criteria;
                },
                gridColumns: [
                    {
                        property: 'name',
                        label: 'Name',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'description',
                        label: 'Description',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'taxType',
                        label: 'Tax calculation',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'active',
                        label: 'Active',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                ],
            },
        },
        shipping_method_prices: {
            id: 'shipping_method_prices',
            associationName: 'shippingMethodPrices',
            notAssignedDataTotal: 0,
            allowAdd: false,
            entityName: 'shipping_method',
            label: 'sw-settings-rule.detail.associations.shippingMethodPrices',
            criteria: () => {
                const criteria = new Criteria(1, associationLimit);
                criteria.addFilter(
                    Criteria.multi(
                        'OR',
                        [
                            Criteria.equals('prices.ruleId', ruleId),
                            Criteria.equals('prices.calculationRuleId', ruleId),
                        ],
                    ),
                );

                return criteria;
            },
            detailRoute: 'sw.settings.shipping.detail',
            gridColumns: [
                {
                    property: 'name',
                    label: 'Name',
                    rawData: true,
                    sortable: true,
                    routerLink: 'sw.settings.shipping.detail',
                    allowEdit: false,
                },
            ],
        },
        payment_method: {
            id: 'payment_method',
            associationName: 'paymentMethods',
            notAssignedDataTotal: 0,
            allowAdd: true,
            entityName: 'payment_method',
            label: 'sw-settings-rule.detail.associations.paymentMethods',
            criteria: () => {
                const criteria = new Criteria(1, associationLimit);
                criteria.addFilter(Criteria.equals('availabilityRuleId', ruleId));

                return criteria;
            },
            detailRoute: 'sw.settings.payment.detail',
            gridColumns: [
                {
                    property: 'name',
                    label: 'Name',
                    rawData: true,
                    sortable: true,
                    routerLink: 'sw.settings.payment.detail',
                    allowEdit: false,
                },
            ],
            deleteContext: {
                type: 'one-to-many',
                entity: 'payment_method',
                column: 'availabilityRuleId',
            },
            addContext: {
                type: 'one-to-many',
                entity: 'payment_method',
                column: 'availabilityRuleId',
                searchColumn: 'name',
                criteria: () => {
                    const criteria = new Criteria(1, 25);
                    criteria.addFilter(Criteria.not(
                        'AND',
                        [Criteria.equals('availabilityRuleId', ruleId)],
                    ));

                    return criteria;
                },
                gridColumns: [
                    {
                        property: 'name',
                        label: 'Name',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'extension',
                        label: 'Extension',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'active',
                        label: 'Active',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'position',
                        label: 'Position',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                ],
            },
        },
        promotion_order_rule: {
            id: 'promotion_order_rule',
            associationName: 'orderPromotions',
            notAssignedDataTotal: 0,
            allowAdd: true,
            entityName: 'promotion',
            label: 'sw-settings-rule.detail.associations.promotionOrderRules',
            criteria: () => {
                const criteria = new Criteria(1, associationLimit);
                criteria.addFilter(Criteria.equals('orderRules.id', ruleId));
                criteria.addAssociation('orderRules');

                return criteria;
            },
            detailRoute: 'sw.promotion.v2.detail.conditions',
            gridColumns: [
                {
                    property: 'name',
                    label: 'Name',
                    rawData: true,
                    sortable: true,
                    routerLink: 'sw.promotion.v2.detail.conditions',
                },
            ],
            deleteContext: {
                type: 'many-to-many',
                entity: 'promotion',
                column: 'orderRules',
            },
            addContext: {
                type: 'many-to-many',
                entity: 'promotion_order_rule',
                column: 'promotionId',
                searchColumn: 'name',
                association: 'orderRules',
                criteria: () => {
                    const criteria = new Criteria(1, 25);
                    criteria.addFilter(Criteria.not('AND', [Criteria.equals('orderRules.id', ruleId)]));

                    return criteria;
                },
                gridColumns: [
                    {
                        property: 'name',
                        label: 'Name',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'active',
                        label: 'Active',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'validFrom',
                        label: 'Valid from',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'validTo',
                        label: 'Valid to',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                ],
            },
        },
        promotion_customer_rule: {
            id: 'promotion_customer_rule',
            associationName: 'personaPromotions',
            notAssignedDataTotal: 0,
            allowAdd: true,
            entityName: 'promotion',
            label: 'sw-settings-rule.detail.associations.promotionCustomerRules',
            criteria: () => {
                const criteria = new Criteria(1, associationLimit);
                criteria.addFilter(Criteria.equals('personaRules.id', ruleId));
                criteria.addAssociation('personaRules');

                return criteria;
            },
            detailRoute: 'sw.promotion.v2.detail.conditions',
            gridColumns: [
                {
                    property: 'name',
                    label: 'Name',
                    rawData: true,
                    sortable: true,
                    routerLink: 'sw.promotion.v2.detail.conditions',
                },
            ],
            deleteContext: {
                type: 'many-to-many',
                entity: 'promotion',
                column: 'personaRules',
            },
            addContext: {
                type: 'many-to-many',
                entity: 'promotion_persona_rule',
                column: 'promotionId',
                searchColumn: 'name',
                association: 'personaRules',
                criteria: () => {
                    const criteria = new Criteria(1, 25);
                    criteria.addFilter(Criteria.not('AND', [Criteria.equals('personaRules.id', ruleId)]));

                    return criteria;
                },
                gridColumns: [
                    {
                        property: 'name',
                        label: 'Name',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'active',
                        label: 'Active',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'validFrom',
                        label: 'Valid from',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'validTo',
                        label: 'Valid to',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                ],
            },
        },
        promotion_cart_rule: {
            id: 'promotion_cart_rule',
            associationName: 'cartPromotions',
            notAssignedDataTotal: 0,
            allowAdd: true,
            entityName: 'promotion',
            label: 'sw-settings-rule.detail.associations.promotionCartRules',
            criteria: () => {
                const criteria = new Criteria(1, associationLimit);
                criteria.addFilter(Criteria.equals('cartRules.id', ruleId));
                criteria.addAssociation('cartRules');

                return criteria;
            },
            detailRoute: 'sw.promotion.v2.detail.conditions',
            gridColumns: [
                {
                    property: 'name',
                    label: 'Name',
                    rawData: true,
                    sortable: true,
                    routerLink: 'sw.promotion.v2.detail.conditions',
                },
            ],
            deleteContext: {
                type: 'many-to-many',
                entity: 'promotion',
                column: 'cartRules',
            },
            addContext: {
                type: 'many-to-many',
                entity: 'promotion_cart_rule',
                column: 'promotionId',
                searchColumn: 'name',
                association: 'cartRules',
                criteria: () => {
                    const criteria = new Criteria(1, 25);
                    criteria.addFilter(Criteria.not('AND', [Criteria.equals('cartRules.id', ruleId)]));

                    return criteria;
                },
                gridColumns: [
                    {
                        property: 'name',
                        label: 'Name',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'active',
                        label: 'Active',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'validFrom',
                        label: 'Valid from',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'validTo',
                        label: 'Valid to',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                ],
            },
        },
        promotion_discount_rule: {
            id: 'promotion_discount_rule',
            associationName: 'promotionDiscounts',
            notAssignedDataTotal: 0,
            allowAdd: false,
            entityName: 'promotion',
            label: 'sw-settings-rule.detail.associations.promotionDiscountRules',
            criteria: () => {
                const criteria = new Criteria(1, associationLimit);
                criteria.addFilter(Criteria.equals('discounts.discountRules.id', ruleId));

                return criteria;
            },
            detailRoute: 'sw.promotion.v2.detail.conditions',
            gridColumns: [
                {
                    property: 'name',
                    label: 'Name',
                    rawData: true,
                    sortable: true,
                    routerLink: 'sw.promotion.v2.detail.conditions',
                },
            ],
        },
        promotion_group_rule: {
            id: 'promotion_group_rule',
            associationName: 'promotionSetGroups',
            notAssignedDataTotal: 0,
            allowAdd: false,
            entityName: 'promotion',
            label: 'sw-settings-rule.detail.associations.promotionGroupRules',
            criteria: () => {
                const criteria = new Criteria(1, associationLimit);
                criteria.addFilter(Criteria.equals('setgroups.setGroupRules.id', ruleId));

                return criteria;
            },
            detailRoute: 'sw.promotion.v2.detail.conditions',
            gridColumns: [
                {
                    property: 'name',
                    label: 'Name',
                    rawData: true,
                    sortable: true,
                    routerLink: 'sw.promotion.v2.detail.conditions',
                },
            ],
        },
        /**
         * @deprecated tag:v6.5.0 - will be removed
         */
        event_action: {
            id: 'event_action',
            associationName: 'eventActions',
            notAssignedDataTotal: 0,
            allowAdd: true,
            entityName: 'event_action',
            label: 'sw-settings-rule.detail.associations.eventActions',
            criteria: () => {
                const criteria = new Criteria(1, associationLimit);
                criteria.addFilter(Criteria.equals('rules.id', ruleId));
                criteria.addAssociation('rules');

                return criteria;
            },
            detailRoute: 'sw.event.action.detail',
            gridColumns: [
                {
                    property: 'eventName',
                    label: 'Business Event',
                    rawData: true,
                    sortable: true,
                    width: '50%',
                    routerLink: 'sw.event.action.detail',
                },
                {
                    property: 'title',
                    label: 'Business Event title',
                    rawData: true,
                    sortable: true,
                    width: '50%',
                    routerLink: 'sw.event.action.detail',
                },
            ],
            deleteContext: {
                type: 'many-to-many',
                entity: 'event_action',
                column: 'rules',
            },
            addContext: {
                type: 'many-to-many',
                entity: 'event_action_rule',
                column: 'eventActionId',
                searchColumn: 'eventName',
                association: 'rules',
                criteria: () => {
                    const criteria = new Criteria(1, 25);
                    criteria.addFilter(Criteria.not('AND', [Criteria.equals('rules.id', ruleId)]));
                    criteria.addFilter(Criteria.equals('actionName', 'action.mail.send'));
                    criteria.addFilter(Criteria.not('AND', [Criteria.equals('config.mail_template_id', null)]));

                    return criteria;
                },
                gridColumns: [
                    {
                        property: 'eventName',
                        label: 'Event',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'title',
                        label: 'Title',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                    {
                        property: 'active',
                        label: 'Active',
                        rawData: true,
                        sortable: true,
                        allowEdit: false,
                    },
                ],
            },
        },
        flow: {
            id: 'flow',
            notAssignedDataTotal: 0,
            allowAdd: false,
            entityName: 'flow',
            label: 'sw-settings-rule.detail.associations.flows',
            criteria: () => {
                const criteria = new Criteria(1, associationLimit);
                criteria.addFilter(Criteria.equals('sequences.rule.id', ruleId));

                return criteria;
            },
            detailRoute: 'sw.flow.detail',
            gridColumns: [
                {
                    property: 'name',
                    label: 'Flow',
                    rawData: true,
                    sortable: true,
                    width: '50%',
                    routerLink: 'sw.flow.detail',
                },
                {
                    property: 'eventName',
                    label: 'Trigger',
                    rawData: true,
                    sortable: true,
                    width: '50%',
                    routerLink: false,
                },
            ],
        },
    };

    return {
        getConfiguration,
    };

    function getConfiguration() {
        return configuration;
    }
}
