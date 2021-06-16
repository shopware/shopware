import template from './sw-condition-currency.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the CurrencyRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-currency :condition="condition" :level="0"></sw-condition-currency>
 */
Component.extend('sw-condition-currency', 'sw-condition-base', {
    template,
    inheritAttrs: false,

    inject: ['repositoryFactory'],

    data() {
        return {
            currencies: null,
        };
    },

    computed: {
        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        currencyIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.currencyIds || [];
            },
            set(currencyIds) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, currencyIds };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.currencyIds']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueCurrencyIdsError;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.currencies = new EntityCollection(
                this.currencyRepository.route,
                this.currencyRepository.entityName,
                Context.api,
            );

            if (this.currencyIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.currencyIds);

            return this.currencyRepository.search(criteria, Context.api).then((currencies) => {
                this.currencies = currencies;
            });
        },

        setCurrencyIds(currencies) {
            this.currencyIds = currencies.getIds();
            this.currencies = currencies;
        },
    },
});
