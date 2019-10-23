import template from './sw-price-rule-modal.html.twig';

const { Component, StateDeprecated } = Shopware;

Component.extend('sw-price-rule-modal', 'sw-rule-modal', {
    template,

    props: {
        priceRuleStore: {
            type: Object,
            required: true
        },
        priceRule: {
            type: Object,
            required: false,
            default: null
        }
    },

    computed: {
        currencyStore() {
            return StateDeprecated.getStore('currency');
        },
        currencySuffix() {
            if (!this.currentCurrency) {
                return null;
            }

            return this.currentCurrency.symbol;
        },
        modalTitle() {
            if (!this.ruleId) {
                return this.$tc('sw-settings-shipping.priceRuleModal.modalTitleNew');
            }
            return this.placeholder(this.rule, 'name', this.$tc('sw-settings-shipping.priceRuleModal.modalTitleModify'));
        },
        currentCurrency() {
            return this.currencies.find(currency => currency.id === this.currentPriceRule.currencyId)
                || this.currencies.find(currency => currency.isSystemDefault);
        }
    },

    data() {
        return {
            currentPriceRule: {},
            currencies: []
        };
    },

    methods: {
        loadEntityData() {
            this.currencyStore.getList().then(currencies => {
                this.currencies = currencies.items;
            });

            if (this.priceRule) {
                this.mapPriceRule();
            } else if (this.ruleId) {
                this.loadPriceRule();
            } else {
                this.createPriceRule();
            }
        },

        mapPriceRule() {
            this.currentPriceRule = this.priceRule;
            if (!this.currentPriceRule.calculationRuleId) {
                this.rule = this.ruleStore.create();
                this.currentPriceRule.calculationRuleId = this.rule.id;
                this.isLoaded = true;
            } else {
                this.ruleStore.getByIdAsync(this.currentPriceRule.calculationRuleId).then(rule => {
                    this.rule = rule;
                    this.isLoaded = true;
                });
            }
        },
        loadPriceRule() {
            this.priceRuleStore.getByIdAsync(this.ruleId).then(priceRule => {
                this.currentPriceRule = priceRule;

                if (this.currentPriceRule.calculationRuleId) {
                    this.ruleStore.getByIdAsync(this.currentPriceRule.calculationRuleId).then(rule => {
                        this.rule = rule;
                        this.isLoaded = true;
                    });
                } else {
                    this.rule = this.ruleStore.create();
                    this.rule.priority = 0;
                    this.currentPriceRule.calculationRuleId = this.rule.id;
                    this.isLoaded = true;
                }
            });
        },
        createPriceRule() {
            this.currentPriceRule = this.priceRuleStore.create();
            this.rule = this.ruleStore.create();
            this.rule.priority = 0;
            this.currentPriceRule.calculationRuleId = this.rule.id;
            this.isLoaded = true;
        },

        emitSave() {
            this.currentPriceRule.currencyId = this.currentCurrency.id;
            this.currentPriceRule.calculationRule = this.rule;
            this.currentPriceRule.original.calculationRule = this.rule;
            this.$emit('save', this.currentPriceRule);
        }
    }
});
