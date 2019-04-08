import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-settings-shipping-price-matrix.html.twig';
import './sw-settings-shipping-price-matrix.scss';

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
            return State.getStore('rule');
        },
        priceRuleStore() {
            return this.shippingMethod.getAssociation('prices');
        },
        labelQuantityStart() {
            const calculationType = {
                1: this.$tc('sw-settings-shipping.priceMatrix.columnQuantityStart'),
                2: this.$tc('sw-settings-shipping.priceMatrix.columnPriceStart'),
                3: this.$tc('sw-settings-shipping.priceMatrix.columnWeightStart'),
                4: this.$tc('sw-settings-shipping.priceMatrix.columnVolumeStart')
            };

            return calculationType[this.priceGroup.calculation]
                || this.$tc('sw-settings-shipping.priceMatrix.columnQuantityStart');
        },
        labelQuantityEnd() {
            const calculationType = {
                1: this.$tc('sw-settings-shipping.priceMatrix.columnQuantityEnd'),
                2: this.$tc('sw-settings-shipping.priceMatrix.columnPriceEnd'),
                3: this.$tc('sw-settings-shipping.priceMatrix.columnWeightEnd'),
                4: this.$tc('sw-settings-shipping.priceMatrix.columnVolumeEnd')
            };

            return calculationType[this.priceGroup.calculation]
                || this.$tc('sw-settings-shipping.priceMatrix.columnQuantityEnd');
        },
        ruleColumns() {
            const columns = [];

            if (this.priceGroup && this.priceGroup.prices.some(priceRule => priceRule.calculationRuleId)) {
                columns.push({
                    property: 'calculationRule.name',
                    dataIndex: 'calculationRule.name',
                    label: this.$tc('sw-settings-shipping.priceMatrix.columnCalculationRule'),
                    allowResize: true,
                    primary: true,
                    rawData: true
                });
            } else {
                columns.push({
                    property: 'quantityStart',
                    dataIndex: 'quantityStart',
                    label: this.labelQuantityStart,
                    inlineEdit: 'number',
                    allowResize: true,
                    primary: true,
                    rawData: true
                });
                columns.push({
                    property: 'quantityEnd',
                    dataIndex: 'quantityEnd',
                    label: this.labelQuantityEnd,
                    inlineEdit: 'number',
                    allowResize: true,
                    rawData: true
                });
            }

            columns.push({
                property: 'price',
                dataIndex: 'price',
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
        }
    },

    data() {
        return {
            ruleFilter: CriteriaFactory.multi(
                'OR',
                CriteriaFactory.contains('rule.moduleTypes.types', 'price'),
                CriteriaFactory.equals('rule.moduleTypes', null)
            ),
            itemAddNewRule: {
                index: -1,
                id: ''
            },
            propertyStore: new LocalStore([
                { name: this.$tc('sw-settings-shipping.priceMatrix.calculationLineItemCount'), value: 1 },
                { name: this.$tc('sw-settings-shipping.priceMatrix.calculationPrice'), value: 2 },
                { name: this.$tc('sw-settings-shipping.priceMatrix.calculationWeight'), value: 3 },
                { name: this.$tc('sw-settings-shipping.priceMatrix.calculationVolume'), value: 4 }
            ], 'value'),
            showRuleModal: false,
            showPriceRuleModal: false,
            priceRuleId: null,
            priceRule: null
        };
    },

    methods: {
        onSelectRule(event) {
            if (event.item.index !== -1) {
                return;
            }

            this.openCreateRuleModal();
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

            this.shippingMethod.prices.push(newPriceRule);
        },
        openCreateRuleModal() {
            this.showRuleModal = true;
        },
        onSaveRule(rule) {
            this.$nextTick(() => this.$emit('rule-change', rule.id, this.priceGroup.ruleId));
        },
        onSavePriceRule(newPriceRule) {
            newPriceRule.shippingMethodId = this.shippingMethod.id;
            newPriceRule.ruleId = this.priceGroup.ruleId;

            if (this.isPlaceholderPriceRule()) {
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
        onCloseRuleModal() {
            this.showRuleModal = false;
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
