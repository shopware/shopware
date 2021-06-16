import template from './sw-settings-shipping-price-matrix.html.twig';
import './sw-settings-shipping-price-matrix.scss';

const { Component, Mixin, Context, Data: { Criteria } } = Shopware;
const { cloneDeep } = Shopware.Utils.object;
const { mapState, mapGetters } = Shopware.Component.getComponentHelper();

Component.register('sw-settings-shipping-price-matrix', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
    ],

    props: {
        priceGroup: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            calculationTypes: [
                { label: this.$tc('sw-settings-shipping.priceMatrix.calculationLineItemCount'), value: 1 },
                { label: this.$tc('sw-settings-shipping.priceMatrix.calculationPrice'), value: 2 },
                { label: this.$tc('sw-settings-shipping.priceMatrix.calculationWeight'), value: 3 },
                { label: this.$tc('sw-settings-shipping.priceMatrix.calculationVolume'), value: 4 },
            ],
            showDeleteModal: false,
            isLoading: false,
        };
    },

    computed: {
        ...mapState('swShippingDetail', [
            'shippingMethod',
            'currencies',
        ]),

        ...mapGetters('swShippingDetail', [
            'defaultCurrency',
            'usedRules',
            'unrestrictedPriceMatrixExists',
            'newPriceMatrixExists',
        ]),

        ruleRepository() {
            return this.repositoryFactory.create('rule');
        },

        shippingPriceRepository() {
            return this.repositoryFactory.create('shipping_method_price');
        },

        labelQuantityStart() {
            const calculationType = {
                1: 'sw-settings-shipping.priceMatrix.columnQuantityStart',
                2: 'sw-settings-shipping.priceMatrix.columnPriceStart',
                3: 'sw-settings-shipping.priceMatrix.columnWeightStart',
                4: 'sw-settings-shipping.priceMatrix.columnVolumeStart',
            };

            return calculationType[this.priceGroup.calculation]
                || 'sw-settings-shipping.priceMatrix.columnQuantityStart';
        },

        labelQuantityEnd() {
            const calculationType = {
                1: 'sw-settings-shipping.priceMatrix.columnQuantityEnd',
                2: 'sw-settings-shipping.priceMatrix.columnPriceEnd',
                3: 'sw-settings-shipping.priceMatrix.columnWeightEnd',
                4: 'sw-settings-shipping.priceMatrix.columnVolumeEnd',
            };

            return calculationType[this.priceGroup.calculation]
                || 'sw-settings-shipping.priceMatrix.columnQuantityEnd';
        },

        numberFieldType() {
            const calculationType = {
                1: 'int',
                2: 'float',
                3: 'float',
                4: 'float',
            };

            return calculationType[this.priceGroup.calculation]
                || 'float';
        },

        confirmDeleteText() {
            const name = this.priceGroup.rule ? this.priceGroup.rule.name : '';
            return this.$tc('sw-settings-shipping.priceMatrix.textDeleteConfirm',
                Number(!!this.priceGroup.rule),
                { name: name });
        },

        ruleColumns() {
            const columns = [];

            if (this.isRuleMatrix) {
                columns.push({
                    property: 'calculationRule',
                    label: 'sw-settings-shipping.priceMatrix.columnCalculationRule',
                    allowResize: true,
                    primary: true,
                    rawData: true,
                    width: '250px',
                });
            } else {
                columns.push({
                    property: 'quantityStart',
                    label: this.labelQuantityStart,
                    allowResize: true,
                    primary: true,
                    rawData: true,
                });
                columns.push({
                    property: 'quantityEnd',
                    label: this.labelQuantityEnd,
                    allowResize: true,
                    rawData: true,
                    primary: true,
                    width: '130px',
                });
            }

            columns.push(...this.currencyColumns);

            return columns;
        },

        currencyColumns() {
            return this.currencies.map((currency) => {
                let label = currency.translated.name || currency.name;
                label = `${label} ${this.$tc('sw-settings-shipping.priceMatrix.labelGrossNet')}`;
                return {
                    property: `price-${currency.isoCode}`,
                    label: label,
                    visible: true,
                    allowResize: true,
                    primary: !!currency.isSystemDefault,
                    rawData: false,
                    width: '200px',
                };
            });
        },

        showDataGrid() {
            return !!this.priceGroup.calculation ||
                this.priceGroup.prices.some(shippingPrice => shippingPrice.calculationRuleId);
        },

        disableDeleteButton() {
            return this.priceGroup.prices.length <= 1;
        },

        ruleFilterCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.multi(
                'OR',
                [
                    Criteria.contains('rule.moduleTypes.types', 'price'),
                    Criteria.equals('rule.moduleTypes', null),
                ],
            ));

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        shippingRuleFilterCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.multi(
                'OR',
                [
                    Criteria.contains('rule.moduleTypes.types', 'shipping'),
                    Criteria.equals('rule.moduleTypes', null),
                ],
            ));

            return criteria;
        },

        isRuleMatrix() {
            return !this.priceGroup.calculation;
        },

        usedCalculationRules() {
            const rules = [];
            if (!this.isRuleMatrix) {
                return rules;
            }

            this.priceGroup.prices.forEach(shippingPrice => {
                if (!rules.includes(shippingPrice.calculationRuleId)) {
                    rules.push(shippingPrice.calculationRuleId);
                }
            });

            return rules;
        },

        mainRulePlaceholder() {
            if (this.priceGroup.isNew) {
                return this.$tc('sw-settings-shipping.priceMatrix.chooseOrCreateRule');
            }

            return this.$tc('sw-settings-shipping.priceMatrix.noRestriction');
        },

        cardTitle() {
            if (!this.priceGroup.rule && !this.priceGroup.isNew) {
                return this.$tc('sw-settings-shipping.priceMatrix.noRestriction');
            }

            return this.priceGroup.rule ? this.priceGroup.rule.name : this.$tc('sw-settings-shipping.priceMatrix.titleCard');
        },
    },

    methods: {
        onAddNewShippingPrice() {
            const refPrice = this.priceGroup.prices[this.priceGroup.prices.length - 1];

            const newShippingPrice = this.shippingPriceRepository.create(Context.api);
            newShippingPrice.shippingMethodId = this.shippingMethod.id;
            newShippingPrice.ruleId = this.priceGroup.ruleId;
            newShippingPrice.currencyPrice = cloneDeep(refPrice.currencyPrice);

            if (refPrice._inNewMatrix) {
                newShippingPrice._inNewMatrix = true;
            }

            if (this.isRuleMatrix) {
                this.shippingMethod.prices.push(newShippingPrice);
                return;
            }

            if (!refPrice.quantityEnd) {
                refPrice.quantityEnd = refPrice.quantityStart;
            }
            newShippingPrice.calculation = refPrice.calculation;

            // If the calculation type is "quantity" always increase by one, otherwise add decimal places
            if (this.priceGroup.calculation === 1) {
                newShippingPrice.quantityStart = refPrice.quantityEnd + 1 > 1 ? refPrice.quantityEnd + 1 : 2;
            } else {
                newShippingPrice.quantityStart = this.increaseWithDecimalPlaces(refPrice.quantityEnd);
            }

            newShippingPrice.quantityEnd = null;

            this.shippingMethod.prices.push(newShippingPrice);
        },

        countDecimalPlaces(value) {
            const split = value.toString().split('.');

            return split[1] ? split[1].length : 0;
        },

        increaseWithDecimalPlaces(value) {
            let decimalPlaces = this.countDecimalPlaces(value);
            decimalPlaces = decimalPlaces === 0 ? 1 : decimalPlaces;

            const increase = Number(`0.${'0'.repeat(decimalPlaces - 1)}1`);
            return Number((value + increase).toFixed(decimalPlaces));
        },

        onSaveMainRule(ruleId) {
            // RuleId can not set to null if there is already an unrestricted rule
            if (!ruleId && this.unrestrictedPriceMatrixExists && this.priceGroup.ruleId !== ruleId) {
                this.createNotificationError({
                    message: this.$tc('sw-settings-shipping.priceMatrix.unrestrictedRuleAlreadyExistsMessage'),
                });
                return;
            }

            this.ruleRepository.get(ruleId, Context.api).then(rule => {
                this.priceGroup.prices.forEach((shippingPrice) => {
                    shippingPrice.ruleId = ruleId;
                    shippingPrice.rule = rule;
                    // Remove "_inNewMatrix" flag, since a rule is now assigned.
                    if (shippingPrice._inNewMatrix) {
                        delete shippingPrice._inNewMatrix;
                    }
                });
            });
        },

        onSaveCustomShippingRule(ruleId, shippingPrice) {
            // If shippingPrice is empty, the first(and only) price of this priceGroup is used - occurs
            // when the priceGroup is new and the user has chosen a custom rule for this group.
            if (!shippingPrice) {
                shippingPrice = this.priceGroup.prices[0];
            }

            // Next tick is necessary because otherwise the modal can not be removed from the dom, since it is moved
            // to the body and Vue cant keep track of it if the parent component is removed(by isLoading)
            this.$nextTick(() => { this.isLoading = true; });
            this.ruleRepository.get(ruleId, Context.api).then(rule => {
                shippingPrice.calculationRuleId = ruleId;
                shippingPrice.calculationRule = rule;
                this.isLoading = false;
            });
        },

        onCalculationChange(calculation) {
            this.priceGroup.prices.forEach(shippingPrice => {
                shippingPrice.calculation = Number(calculation);
                shippingPrice.ruleId = this.priceGroup.ruleId;
            });
        },

        onDeletePriceMatrix() {
            this.showDeleteModal = true;
        },

        onConfirmDeleteShippingPrice() {
            this.showDeleteModal = false;
            this.$nextTick(() => {
                this.$emit('delete-price-matrix', this.priceGroup);
            });
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onDeleteShippingPrice(shippingPrice) {
            // if it is the only item in the priceGroup
            if (this.priceGroup.prices.length <= 1) {
                this.createNotificationInfo({
                    message: this.$tc('sw-settings-shipping.priceMatrix.deletionNotPossibleMessage'),
                });

                return;
            }

            // get actual rule index
            const actualShippingPriceIndex = this.priceGroup.prices.indexOf(shippingPrice);

            // if it is the last item
            if (typeof shippingPrice.quantityEnd === 'undefined' || shippingPrice.quantityEnd === null) {
                // get previous rule
                const previousRule = this.priceGroup.prices[actualShippingPriceIndex - 1];

                // set the quantityEnd from the previous rule to null
                previousRule.quantityEnd = null;
            } else {
                // get next rule
                const nextRule = this.priceGroup.prices[actualShippingPriceIndex + 1];

                // set the quantityStart from the next rule to the quantityStart from the actual rule
                nextRule.quantityStart = shippingPrice.quantityStart;
            }

            // delete rule
            this.shippingMethod.prices.remove(shippingPrice.id);
        },

        convertDefaultPriceToCurrencyPrice(item, currency) {
            if (!item.currencyPrice) {
                this.initCurrencyPrice(item);
            }

            const defaultPrice = item.currencyPrice.find(price => {
                return price.currencyId === this.defaultCurrency.id;
            });

            return this.convertPrice(defaultPrice, currency);
        },

        /**
         * Initialises the currencyPrice field with the default currency
         */
        initCurrencyPrice(shippingPrice) {
            shippingPrice.currencyPrice = [{
                currencyId: this.defaultCurrency.id,
                gross: 0,
                linked: false,
                net: 0,
            }];
        },

        getPrice(shippingPrice, currency) {
            const currencyPrice = this.getPriceOfCurrency(shippingPrice, currency);
            if (currencyPrice) {
                return currencyPrice;
            }

            return null;
        },

        setPrice(shippingPrice, currency, value) {
            if (!value) {
                shippingPrice.currencyPrice = shippingPrice.currencyPrice.filter(price => {
                    return price.currencyId !== currency.id;
                });
                return;
            }

            const price = {
                currencyId: currency.id,
                gross: value.gross,
                linked: false,
                net: value.net,
            };
            shippingPrice.currencyPrice.push(price);
        },

        getPriceOfCurrency(priceArray, currency) {
            if (!priceArray.currencyPrice) {
                this.initCurrencyPrice(priceArray);
            }

            return priceArray.currencyPrice.find(price => {
                return price.currencyId === currency.id;
            });
        },

        convertPrice(value, currency) {
            return {
                net: value.net * currency.factor,
                gross: value.gross * currency.factor,
                currencyId: currency.id,
                linked: false,
            };
        },

        onQuantityEndChange(price) {
            // when not last price
            if (this.priceGroup.prices.indexOf(price) + 1 !== this.priceGroup.prices.length) {
                return;
            }

            this.onAddNewShippingPrice();
        },
    },
});
