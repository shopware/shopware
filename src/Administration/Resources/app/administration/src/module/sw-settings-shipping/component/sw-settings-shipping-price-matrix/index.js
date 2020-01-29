import LocalStore from 'src/core/data/LocalStore';
import template from './sw-settings-shipping-price-matrix.html.twig';
import './sw-settings-shipping-price-matrix.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-shipping-price-matrix', {
    template,

    mixins: [
        Mixin.getByName('placeholder')
    ],

    props: {
        priceGroup: {
            type: Object,
            required: true
        },
        priceRuleGroups: {
            type: Object,
            required: true
        },
        shippingMethod: {
            type: Object,
            required: true
        }
    },

    computed: {
        ruleStore() {
            return StateDeprecated.getStore('rule');
        },
        priceRuleStore() {
            return this.shippingMethod.getAssociation('prices');
        },
        labelQuantityStart() {
            const calculationType = {
                1: 'sw-settings-shipping.priceMatrix.columnQuantityStart',
                2: 'sw-settings-shipping.priceMatrix.columnPriceStart',
                3: 'sw-settings-shipping.priceMatrix.columnWeightStart',
                4: 'sw-settings-shipping.priceMatrix.columnVolumeStart'
            };

            return calculationType[this.priceGroup.calculation]
                || 'sw-settings-shipping.priceMatrix.columnQuantityStart';
        },
        labelQuantityEnd() {
            const calculationType = {
                1: 'sw-settings-shipping.priceMatrix.columnQuantityEnd',
                2: 'sw-settings-shipping.priceMatrix.columnPriceEnd',
                3: 'sw-settings-shipping.priceMatrix.columnWeightEnd',
                4: 'sw-settings-shipping.priceMatrix.columnVolumeEnd'
            };

            return calculationType[this.priceGroup.calculation]
                || 'sw-settings-shipping.priceMatrix.columnQuantityEnd';
        },
        confirmDeleteText() {
            const name = this.priceGroup.rule ? this.priceGroup.rule.name : '';
            return this.$tc('sw-settings-shipping.priceMatrix.textDeleteConfirm',
                Number(!!this.priceGroup.rule),
                { name: name });
        },
        ruleColumns() {
            const columns = [];

            if (this.priceGroup && this.priceGroup.prices.some(priceRule => priceRule.calculationRuleId)) {
                columns.push({
                    property: 'calculationRule.name',
                    label: 'sw-settings-shipping.priceMatrix.columnCalculationRule',
                    allowResize: true,
                    primary: true,
                    rawData: true
                });
            } else {
                columns.push({
                    property: 'quantityStart',
                    label: this.labelQuantityStart,
                    inlineEdit: 'number',
                    allowResize: true,
                    primary: true,
                    rawData: true
                });
                columns.push({
                    property: 'quantityEnd',
                    label: this.labelQuantityEnd,
                    inlineEdit: 'number',
                    allowResize: true,
                    rawData: true
                });
            }

            columns.push({
                property: 'price',
                label: this.$tc('sw-settings-shipping.priceMatrix.columnPrice'),
                inlineEdit: 'number',
                allowResize: true,
                rawData: true
            });

            return columns;
        },
        allowInlineEdit() {
            return !this.priceGroup.prices.some(priceRule => priceRule.calculationRuleId);
        },
        showDataGrid() {
            return this.priceGroup.calculation || this.priceGroup.prices.some(priceRule => priceRule.calculationRuleId);
        },
        disableDeleteButton() {
            return this.priceGroup.prices.length <= 1;
        },
        hasNoRuleMatrix() {
            return Object.values(this.priceRuleGroups).some((priceGroup) => {
                return !priceGroup.ruleId;
            });
        },

        ruleFilter() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.multi(
                'OR',
                [
                    Criteria.contains('rule.moduleTypes.types', 'price'),
                    Criteria.equals('rule.moduleTypes', null)
                ]
            ));

            return criteria;
        }
    },

    data() {
        return {
            propertyStore: new LocalStore([
                { name: this.$tc('sw-settings-shipping.priceMatrix.calculationLineItemCount'), value: 1 },
                { name: this.$tc('sw-settings-shipping.priceMatrix.calculationPrice'), value: 2 },
                { name: this.$tc('sw-settings-shipping.priceMatrix.calculationWeight'), value: 3 },
                { name: this.$tc('sw-settings-shipping.priceMatrix.calculationVolume'), value: 4 }
            ], 'value'),
            showDeleteModal: false,
            showPriceRuleModal: false,
            priceRuleId: null,
            priceRule: null
        };
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.loadCalculationRules();
        },

        loadCalculationRules() {
            this.priceGroup.prices.forEach(price => {
                if (!price.calculationRuleId || price.calculationRule.id) {
                    return;
                }

                this.ruleStore.getByIdAsync(price.calculationRuleId).then(calculationRule => {
                    price.calculationRule = calculationRule;
                    Object.assign(price.original.calculationRule, calculationRule);
                });
            });
        },

        onAddNewPriceRule() {
            const price = this.priceGroup.prices[this.priceGroup.prices.length - 1];

            if (price.calculationRuleId) {
                this.openCreatePriceRuleModal();
                return;
            }

            if (!price.quantityEnd) {
                price.quantityEnd = price.quantityStart + 1;
            }
            const newPriceRule = this.priceRuleStore.create();
            newPriceRule.shippingMethodId = this.shippingMethod.id;
            newPriceRule.ruleId = this.priceGroup.ruleId;
            newPriceRule.quantityStart = price.quantityEnd;
            newPriceRule.quantityEnd = null;
            newPriceRule.currencyId = price.currencyId;
            newPriceRule.price = price.price;
            newPriceRule.calculation = price.calculation;

            this.shippingMethod.prices.push(newPriceRule);
        },
        onSaveRule(ruleId) {
            this.$nextTick(() => {
                this.$emit('rule-add');
                this.$emit('rule-change', ruleId, this.priceGroup.ruleId);
            });
        },
        onSavePriceRule(newPriceRule) {
            newPriceRule.shippingMethodId = this.shippingMethod.id;
            newPriceRule.ruleId = this.priceGroup.ruleId;

            if (this.isPlaceholderPriceRule()) {
                this.priceRuleStore.remove(this.priceGroup.prices[0]);
                Object.assign(this.priceGroup.prices[0], newPriceRule);
            } else if (!this.priceGroup.prices.some(priceRule => priceRule.id === newPriceRule.id)) {
                this.shippingMethod.prices.push(newPriceRule);
            }
        },
        isPlaceholderPriceRule() {
            if (this.priceGroup.prices.length > 1) {
                return false;
            }

            const priceRule = this.priceGroup.prices[0];
            return !(priceRule.calculation || priceRule.calculationRuleId);
        },
        onClosePriceRuleModal() {
            this.showPriceRuleModal = false;
            this.priceRule = null;
        },
        openCreatePriceRuleModal() {
            this.priceRuleId = null;
            this.showPriceRuleModal = true;
        },
        onModifyPriceRule(item) {
            this.priceRuleId = item.calculationRuleId;
            this.priceRule = item;
            this.showPriceRuleModal = true;
        },
        onCalculationChange(calculation) {
            this.priceGroup.prices.forEach(priceRule => {
                priceRule.calculation = Number(calculation);
                priceRule.ruleId = this.priceGroup.ruleId;
            });
        },
        onDuplicatePriceRule(priceRule) {
            const newPriceRule = this.priceRuleStore.duplicate(priceRule.id);
            this.shippingMethod.prices.push(newPriceRule);
        },
        onDeletePriceMatrix() {
            this.showDeleteModal = true;
        },
        onConfirmDeletePriceRule() {
            this.showDeleteModal = false;
            this.$nextTick(() => {
                this.$emit('delete-price-matrix', this.priceGroup);
            });
        },
        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onDeletePriceRule(priceRule) {
            // Do not delete the last price
            const priceRuleGroup = this.priceRuleGroups[priceRule.ruleId];

            if (priceRuleGroup.prices.length <= 1) {
                return;
            }

            this.shippingMethod.prices = this.shippingMethod.prices.filter((price) => {
                return price.id !== priceRule.id;
            });

            this.priceRuleStore.getById(priceRule.id).delete();
        },
        onSaveInlineEdit(item) {
            if (item !== this.priceGroup.prices[this.priceGroup.prices.length - 1]
                || !item.quantityEnd) {
                return;
            }

            this.onAddNewPriceRule();
        }
    }
});
