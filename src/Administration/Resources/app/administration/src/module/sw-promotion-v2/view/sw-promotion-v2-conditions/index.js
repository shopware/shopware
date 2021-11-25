import template from './sw-promotion-v2-conditions.html.twig';

const { Component } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;
const types = Shopware.Utils.types;

Component.register('sw-promotion-v2-conditions', {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
        'ruleConditionDataProviderService',
    ],

    props: {
        promotion: {
            type: Object,
            required: false,
            default() {
                return null;
            },
        },
    },

    data() {
        return {
            excludedPromotions: this.createPromotionCollection(),
            /* @internal (flag:FEATURE_NEXT_18215) */
            personaRestrictedRules: [],
            /* @internal (flag:FEATURE_NEXT_18215) */
            orderRestrictedRules: [],
            /* @internal (flag:FEATURE_NEXT_18215) */
            cartRestrictedRules: [],
        };
    },

    computed: {
        exclusionCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.not('and', [Criteria.equals('id', this.promotion.id)]));
            return criteria;
        },

        personaRuleFilter() {
            const criteria = new Criteria();

            if (!this.feature.isActive('FEATURE_NEXT_18215')) {
                criteria.addFilter(Criteria.multi('AND', [
                    Criteria.not('AND', [Criteria.equalsAny('conditions.type', ['cartCartAmount'])]),
                    Criteria.equalsAny('conditions.type', [
                        'customerBillingCountry', 'customerBillingStreet', 'customerBillingZipCode', 'customerIsNewCustomer',
                        'customerCustomerGroup', 'customerCustomerNumber', 'customerDaysSinceLastOrder',
                        'customerDifferentAddresses', 'customerLastName', 'customerOrderCount', 'customerShippingCountry',
                        'customerShippingStreet', 'customerShippingZipCode',
                    ]),
                ]));
            }

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        cartConditionsRuleFilter() {
            const criteria = new Criteria();

            if (!this.feature.isActive('FEATURE_NEXT_18215')) {
                criteria.addFilter(
                    Criteria.not('AND', [Criteria.equalsAny('conditions.type', ['cartCartAmount'])]),
                );
            }

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        orderConditionsFilter() {
            const criteria = new Criteria();

            if (!this.feature.isActive('FEATURE_NEXT_18215')) {
                criteria.addFilter(Criteria.multi('AND', [
                    Criteria.equalsAny('conditions.type', [
                        'customerOrderCount', 'customerDaysSinceLastOrder', 'customerBillingCountry',
                        'customerBillingStreet', 'customerBillingZipCode', 'customerCustomerGroup',
                        'customerCustomerNumber', 'customerDifferentAddresses', 'customerIsNewCustomer',
                        'customerLastName', 'customerShippingCountry', 'customerShippingStreet',
                        'customerShippingZipCode',
                    ]),
                    Criteria.not('AND', [Criteria.equalsAny('conditions.type', ['cartCartAmount'])]),
                ]));
            }

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadExclusions();

            if (this.feature.isActive('FEATURE_NEXT_18215')) {
                this.ruleConditionDataProviderService.getRestrictedRules('personaPromotions')
                    .then((result) => { this.personaRestrictedRules = result; });

                this.ruleConditionDataProviderService.getRestrictedRules('orderPromotions')
                    .then((result) => { this.orderRestrictedRules = result; });

                this.ruleConditionDataProviderService.getRestrictedRules('cartPromotions')
                    .then((result) => { this.cartRestrictedRules = result; });
            }
        },

        loadExclusions() {
            if (types.isEmpty(this.promotion.exclusionIds)) {
                this.excludedPromotions = this.createPromotionCollection();
                return;
            }

            const promotionRepository = this.repositoryFactory.create('promotion');
            const criteria = (new Criteria()).addFilter(Criteria.equalsAny('id', this.promotion.exclusionIds));

            promotionRepository.search(criteria).then((excluded) => {
                this.excludedPromotions = excluded;
            });
        },

        onChangeExclusions(promotions) {
            this.promotion.exclusionIds = promotions.map((promotion) => {
                return promotion.id;
            });

            this.loadExclusions();
        },

        createPromotionCollection() {
            return new EntityCollection('/promotion', 'promotion', Shopware.Context.api, new Criteria());
        },
    },
});
