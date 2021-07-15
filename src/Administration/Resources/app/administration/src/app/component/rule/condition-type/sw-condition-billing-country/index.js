import template from './sw-condition-billing-country.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the BillingCountryRule. This component must a be child of sw-condition-tree.
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-condition-billing-country :condition="condition"></sw-condition-billing-country>
 */
Component.extend('sw-condition-billing-country', 'sw-condition-base', {
    template,
    inheritAttrs: false,

    inject: ['repositoryFactory'],

    data() {
        return {
            billingCountries: null,
            inputKey: 'countryIds',
        };
    },

    computed: {
        countryRepository() {
            return this.repositoryFactory.create('country');
        },

        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('multiStore'),
            );
        },

        countryIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.countryIds || [];
            },
            set(countryIds) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, countryIds };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.countryIds']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueCountryIdsError;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.billingCountries = new EntityCollection(
                this.countryRepository.route,
                this.countryRepository.entityName,
                Context.api,
            );

            if (this.countryIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.countryIds);

            return this.countryRepository.search(criteria, Context.api).then((countries) => {
                this.billingCountries = countries;
            });
        },

        setCountryIds(countries) {
            this.countryIds = countries.getIds();
            this.billingCountries = countries;
        },
    },
});
