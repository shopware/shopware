import template from './sw-settings-rule-detail-assignments.html.twig';
import './sw-settings-rule-detail-assignments.scss';

const { Component, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-rule-detail-assignments', {
    template,

    inject: [
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        rule: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            associationLimit: 5,
            isLoading: false,
            ruleAssociationsLoaded: false,
            products: null,
            shippingMethods: null,
            paymentMethods: null,
            promotions: null,
            eventActions: null,
            associationSteps: [5, 10],
            associationEntities: null,
        };
    },

    computed: {
        hasNoAssociations() {
            return this.associationEntities.every((entity) => {
                return entity.loadedData && entity.loadedData.total === 0;
            });
        },

        /* eslint-disable max-len */
        /**
         * Definition of the associated entities of the current rule.
         * The component will render a sw-entity-listing for each association entity,
         * if results are given.
         *
         * @type {[{entityName: String, label: String, api: Function, criteria: Function, detailRoute: String, gridColumns: Array<Object>}]}
         * @returns {Array<Object>}
         */
        /* eslint-enable max-len */
        associationEntitiesConfig() {
            return [
                {
                    entityName: 'product',
                    label: this.$tc('sw-settings-rule.detail.associations.products'),
                    criteria: () => {
                        const criteria = new Criteria();
                        criteria.setLimit(this.associationLimit);
                        criteria.addFilter(Criteria.equals('prices.rule.id', this.rule.id));

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
                            sortable: false,
                            routerLink: 'sw.product.detail.prices',
                            allowEdit: false,
                        },
                    ],
                },
                {
                    entityName: 'shipping_method',
                    label: this.$tc('sw-settings-rule.detail.associations.shippingMethods'),
                    criteria: () => {
                        const criteria = new Criteria();
                        criteria.setLimit(this.associationLimit);
                        criteria.addFilter(
                            Criteria.multi(
                                'OR',
                                [
                                    Criteria.equals('prices.ruleId', this.rule.id),
                                    Criteria.equals('prices.calculationRuleId', this.rule.id),
                                    Criteria.equals('availabilityRuleId', this.rule.id),
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
                            sortable: false,
                            routerLink: 'sw.settings.shipping.detail',
                            allowEdit: false,
                        },
                    ],
                },
                {
                    entityName: 'payment_method',
                    label: this.$tc('sw-settings-rule.detail.associations.paymentMethods'),
                    criteria: () => {
                        const criteria = new Criteria();
                        criteria.setLimit(this.associationLimit);
                        criteria.addFilter(Criteria.equals('availabilityRuleId', this.rule.id));

                        return criteria;
                    },
                    detailRoute: 'sw.settings.payment.detail',
                    gridColumns: [
                        {
                            property: 'name',
                            label: 'Name',
                            rawData: true,
                            sortable: false,
                            routerLink: 'sw.settings.payment.detail',
                            allowEdit: false,
                        },
                    ],
                },
                {
                    entityName: 'promotion',
                    label: this.$tc('sw-settings-rule.detail.associations.promotions'),
                    criteria: () => {
                        const criteria = new Criteria();
                        criteria.setLimit(this.associationLimit);
                        criteria.addFilter(
                            Criteria.multi(
                                'OR',
                                [
                                    Criteria.equals('personaRules.id', this.rule.id),
                                    Criteria.equals('orderRules.id', this.rule.id),
                                    Criteria.equals('cartRules.id', this.rule.id),
                                    Criteria.equals('discounts.discountRules.id', this.rule.id),
                                    Criteria.equals('setgroups.setGroupRules.id', this.rule.id),
                                ],
                            ),
                        );

                        return criteria;
                    },
                    detailRoute: 'sw.promotion.v2.detail.conditions',
                    gridColumns: [
                        {
                            property: 'name',
                            label: 'Name',
                            rawData: true,
                            sortable: false,
                            routerLink: 'sw.promotion.v2.detail.conditions',
                        },
                    ],
                },
                {
                    entityName: 'event_action',
                    label: this.$tc('sw-settings-rule.detail.associations.eventActions'),
                    criteria: () => {
                        const criteria = new Criteria();
                        criteria.setLimit(this.associationLimit);
                        criteria.addFilter(Criteria.equals('rules.id', this.rule.id));

                        return criteria;
                    },
                    detailRoute: 'sw.event.action.detail',
                    gridColumns: [
                        {
                            property: 'eventName',
                            label: 'Business Event',
                            rawData: true,
                            sortable: false,
                            width: '50%',
                            routerLink: 'sw.event.action.detail',
                        },
                        {
                            property: 'title',
                            label: 'Business Event Title',
                            rawData: true,
                            sortable: false,
                            width: '50%',
                            routerLink: 'sw.event.action.detail',
                        },
                    ],
                },
            ];
        },

        loadedAssociationEntities() {
            return this.associationEntities.filter((item) => {
                return item.loadedData && item.loadedData.total > 0;
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.prepareAssociationEntitiesList();
            this.loadAssociationData();
        },

        prepareAssociationEntitiesList() {
            this.associationEntities = this.associationEntitiesConfig.map((item) => {
                return {
                    repository: this.repositoryFactory.create(item.entityName),
                    loadedData: null,
                    ...item,
                };
            });
        },

        loadAssociationData() {
            this.isLoading = true;

            return Promise
                .all(this.associationEntities.map((item) => {
                    const api = item.api ? item.api() : Context.api;

                    return item.repository.search(item.criteria(), api).then((result) => {
                        item.loadedData = result;
                    });
                }))
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-rule.detail.associationsLoadingError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
