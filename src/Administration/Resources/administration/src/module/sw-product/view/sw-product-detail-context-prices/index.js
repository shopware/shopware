import { Component, Mixin } from 'src/core/shopware';
import { mapState, mapGetters } from 'vuex';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-product-detail-context-prices.html.twig';
import './sw-product-detail-context-prices.scss';

Component.register('sw-product-detail-context-prices', {
    template,

    inject: ['repositoryFactory', 'context'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            rules: [],
            totalRules: 0
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'repositoryFactory',
            'context',
            'product',
            'taxes',
            'currencies'
        ]),

        ...mapGetters('swProductDetail', [
            'isLoading'
        ]),

        priceRepository() {
            if (this.product && this.product.prices) {
                return this.repositoryFactory.create(
                    this.product.prices.entity,
                    this.product.prices.source
                );
            }
            return null;
        },

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        priceRuleGroups() {
            const priceRuleGroups = {};

            if (!this.product.prices || !this.product.prices.items) {
                return priceRuleGroups;
            }

            if (!this.rules || !this.rules.items) {
                return priceRuleGroups;
            }

            Object.values(this.product.prices.items).forEach((rule) => {
                if (!priceRuleGroups[rule.ruleId]) {
                    priceRuleGroups[rule.ruleId] = {
                        ruleId: rule.ruleId,
                        rule: this.findRuleById(rule.ruleId),
                        currencies: {}
                    };
                }

                if (!priceRuleGroups[rule.ruleId].currencies[rule.currencyId]) {
                    priceRuleGroups[rule.ruleId].currencies[rule.currencyId] = {
                        currencyId: rule.currencyId,
                        currency: this.findCurrencyById(rule.currencyId),
                        prices: []
                    };
                }

                priceRuleGroups[rule.ruleId].currencies[rule.currencyId].prices.push(rule);
            });

            // Sort prices for quantity
            Object.values(priceRuleGroups).forEach((priceRule) => {
                Object.values(priceRule.currencies).forEach((currency) => {
                    currency.prices = currency.prices.sort((a, b) => {
                        return a.quantityStart - b.quantityStart;
                    });
                });
            });

            return priceRuleGroups;
        },

        canAddPriceRule() {
            const usedRules = Object.keys(this.priceRuleGroups).length;
            const availableRules = this.rules.length;

            return usedRules !== availableRules;
        },

        isLoaded() {
            return !this.isLoading &&
                   !this.isLoadingRules &&
                   this.currencies.items &&
                   this.taxes.items &&
                   this.product;
        },

        productTaxRate() {
            return Object.values(this.taxes.items).find((taxRate) => {
                return taxRate.id === this.product.taxId;
            });
        },

        defaultCurrency() {
            return Object.values(this.currencies.items).find((currency) => {
                return currency.isDefault;
            });
        }
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            const ruleCriteria = new Criteria(1, 500);
            ruleCriteria.addFilter(
                Criteria.multi('OR', [
                    Criteria.contains('rule.moduleTypes.types', 'price'),
                    Criteria.equals('rule.moduleTypes', null)
                ])
            );

            this.$store.commit('swProductDetail/setLoading', ['rules', true]);
            this.ruleRepository.search(ruleCriteria, this.context).then((res) => {
                this.rules = res;
                this.totalRules = res.total;

                this.$store.commit('swProductDetail/setLoading', ['rules', false]);
            });
        },

        onRuleChange(value, ruleId) {
            this.product.prices.forEach((priceRule) => {
                if (priceRule.ruleId === ruleId) {
                    priceRule.ruleId = value;
                }
            });
        },

        onAddNewPriceGroup() {
            if (typeof this.priceRuleGroups.null !== 'undefined') {
                return;
            }

            const newPriceRule = this.priceRepository.create(this.context);

            newPriceRule.ruleId = null;
            newPriceRule.productId = this.product.id;
            newPriceRule.quantityStart = 1;
            newPriceRule.quantityEnd = null;
            newPriceRule.currencyId = this.defaultCurrency.id;
            newPriceRule.price = {
                gross: null,
                linked: true,
                net: null
            };

            this.product.prices.add(newPriceRule);
        },

        onAddCurrency(ruleId, currency) {
            const defaultCurrencyPrices = this.priceRuleGroups[ruleId].currencies[this.defaultCurrency.id].prices;

            defaultCurrencyPrices.forEach((price) => {
                this.duplicatePriceRule(price.id).then((newPriceRule) => {
                    // set the the new currency id
                    newPriceRule.currencyId = currency.id;

                    this.product.prices.add(newPriceRule);
                });
            });
        },

        onPriceGroupDelete(ruleId) {
            Object.values(this.product.prices.items).forEach((item) => {
                if (item.ruleId === ruleId) {
                    this.product.prices.remove(item.id);
                }
            });
        },

        onPriceGroupDuplicate(priceGroup) {
            if (typeof this.priceRuleGroups.null !== 'undefined') {
                return;
            }

            const priceIdsToDuplicate = Object.keys(priceGroup.currencies).reduce((acc, currencyId) => {
                const priceIds = priceGroup.currencies[currencyId].prices.map((price) => {
                    return { priceId: price.id, currencyId };
                });
                acc = [...acc, ...priceIds];
                return acc;
            }, []);

            const duplicatePromises = priceIdsToDuplicate.map((price) => {
                return new Promise((resolve) => {
                    this.duplicatePriceRule(price.priceId).then((newPriceRule) => {
                        newPriceRule.ruleId = null;
                        newPriceRule.currencyId = price.currencyId;
                        resolve(newPriceRule);
                    });
                });
            });

            Promise.all(duplicatePromises).then((newPriceRules) => {
                newPriceRules.forEach((newPriceRule) => {
                    this.product.prices.add(newPriceRule);
                });
            });
        },

        onPriceRuleDuplicate(priceRule) {
            this.duplicatePriceRule(priceRule.id).then((newPriceRule) => {
                newPriceRule.currencyId = priceRule.currencyId;
                this.product.prices.add(newPriceRule);
            });
        },

        onPriceRuleDelete(priceRule) {
            // Do not delete the last price of the default currency
            if (priceRule.currencyId === this.defaultCurrency.id) {
                const priceRuleGroup = this.priceRuleGroups[priceRule.ruleId];
                const defaultCurrencyPrices = priceRuleGroup.currencies[this.defaultCurrency.id].prices;

                if (defaultCurrencyPrices.length <= 1 && Object.keys(priceRuleGroup.currencies.items).length >= 1) {
                    this.createNotificationError({
                        title: this.$tc('sw-product.advancedPrices.deletionNotPossibleTitle'),
                        message: this.$tc('sw-product.advancedPrices.deletionNotPossibleMessage')
                    });
                    return;
                }
            }

            const priceRuleToDelete = Object.values(this.product.prices.items).find((price) => price.id === priceRule.id);
            this.product.prices.remove(priceRuleToDelete.id);
        },

        onQuantityEndChange(value, price, priceGroup) {
            const currencyPrices = priceGroup.currencies[price.currencyId].prices;

            if (!currencyPrices.length) {
                return;
            }

            if (currencyPrices[currencyPrices.length - 1].id === price.id && value !== null) {
                const newPriceRule = this.priceRepository.create(this.context);

                newPriceRule.productId = this.product.id;
                newPriceRule.ruleId = priceGroup.ruleId;
                newPriceRule.quantityStart = price.quantityEnd + 1;
                newPriceRule.quantityEnd = null;
                newPriceRule.currencyId = price.currencyId;
                newPriceRule.price = {
                    gross: null,
                    linked: true,
                    net: null
                };

                this.product.prices.add(newPriceRule);
            }
        },

        findRuleById(ruleId) {
            return Object.values(this.rules.items).find((rule) => {
                return rule.id === ruleId;
            });
        },

        findCurrencyById(currencyId) {
            return Object.values(this.currencies.items).find((currency) => {
                return currency.id === currencyId;
            });
        },

        duplicatePriceRule(referencePriceId) {
            return new Promise((resolve) => {
                const ref = this.product.prices.get(referencePriceId);
                const newPriceRule = this.priceRepository.create(this.context);

                // copy the values from reference
                newPriceRule.productId = ref.productId;
                newPriceRule.productVersionId = ref.productVersionId;
                newPriceRule.quantityEnd = ref.quantityEnd;
                newPriceRule.quantityStart = ref.quantityStart;
                newPriceRule.ruleId = ref.ruleId;
                newPriceRule.versionId = ref.versionId;
                newPriceRule.price = { ...ref.price };

                resolve(newPriceRule);
            });
        }
    }
});
