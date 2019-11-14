import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-settings-shipping-price-matrices.html.twig';
import './sw-settings-shipping-price-matrices.scss';

const { Component, Mixin, StateDeprecated } = Shopware;

Component.register('sw-settings-shipping-price-matrices', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    props: {
        shippingMethod: {
            type: Object,
            required: true
        }
    },

    computed: {
        ruleStore() {
            return StateDeprecated.getStore('rule');
        },
        ruleFilter() {
            return CriteriaFactory.multi('OR',
                CriteriaFactory.contains('rule.moduleTypes.types', 'price'),
                CriteriaFactory.equals('rule.moduleTypes', null));
        },
        priceRuleStore() {
            return this.shippingMethod.getAssociation('prices');
        },

        currencyStore() {
            return StateDeprecated.getStore('currency');
        },

        priceRuleGroups() {
            const priceRuleGroups = {};

            this.shippingMethod.prices.forEach((rule) => {
                if (this.priceRuleStore.getById(rule.id).isDeleted) {
                    return;
                }

                if (!priceRuleGroups[rule.ruleId]) {
                    priceRuleGroups[rule.ruleId] = {
                        ruleId: rule.ruleId,
                        rule: this.findRuleById(rule.ruleId),
                        currencyId: rule.currencyId,
                        currency: this.findCurrencyById(rule.currencyId),
                        calculation: rule.calculation,
                        prices: []
                    };
                }

                priceRuleGroups[rule.ruleId].prices.push(rule);
            });

            return priceRuleGroups;
        },

        canAddPriceRule() {
            const usedRules = Object.keys(this.priceRuleGroups).length;
            const availableRules = this.rules.length;

            const priceRuleWithoutRule = this.shippingMethod.prices.find(rule => {
                return !rule.ruleId;
            });

            return !priceRuleWithoutRule && usedRules !== availableRules;
        },

        isLoaded() {
            return !this.isLoadingRules &&
                this.currencies.length &&
                this.shippingMethod;
        },

        defaultCurrency() {
            return this.currencies.find(currency => currency.isSystemDefault);
        }
    },

    data() {
        return {
            currencies: [],
            rules: [],
            totalRules: 0,
            isLoadingRules: false
        };
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyComponent();
    },

    methods: {
        createdComponent() {
            this.currencyStore.getList({
                page: 1,
                limit: 500
            }).then((currencyResponse) => {
                this.currencies = currencyResponse.items;

                this.priceRuleStore.getList({
                    page: 1,
                    limit: 500
                }).then((priceResponse) => {
                    if (priceResponse.total === 0) {
                        this.onAddNewPriceGroup();
                    }
                });
            });

            this.loadRules();

            this.$on('rule-add', this.loadRules);
        },

        beforeDestroyComponent() {
            this.$off('rule-add');
        },

        loadRules() {
            this.isLoadingRules = true;

            this.ruleStore.getList({
                page: 1,
                limit: 500,
                criteria: this.ruleFilter
            }).then((response) => {
                this.rules = response.items;
                this.totalRules = response.total;

                this.isLoadingRules = false;
            });
        },

        onAddNewPriceGroup() {
            const newPriceRule = this.priceRuleStore.create();
            newPriceRule.shippingMethodId = this.shippingMethod.id;
            newPriceRule.currencyId = this.defaultCurrency.id;

            this.shippingMethod.prices.push(newPriceRule);
        },

        onDeletePriceMatrix(priceRuleGroup) {
            this.priceRuleStore.forEach((item) => {
                if (item.ruleId === priceRuleGroup.ruleId) {
                    item.delete();
                }
            });

            this.shippingMethod.prices = this.shippingMethod.prices.filter((priceRule) => {
                return priceRule.ruleId !== priceRuleGroup.ruleId;
            });
        },

        onDuplicatePriceMatrix(priceGroup) {
            priceGroup.prices.forEach(price => {
                const newPriceRule = this.priceRuleStore.duplicate(price.id);
                newPriceRule.ruleId = null;

                this.shippingMethod.prices.push(newPriceRule);
            });
        },

        onRuleChange(value, ruleId) {
            this.shippingMethod.prices.forEach((priceRule) => {
                if (priceRule.ruleId === ruleId) {
                    priceRule.ruleId = value;
                }
            });
        },

        findRuleById(ruleId) {
            return this.rules.find((rule) => {
                return rule.id === ruleId;
            });
        },

        findCurrencyById(currencyId) {
            return this.currencies.find((currency) => {
                return currency.id === currencyId;
            });
        }
    }
});
