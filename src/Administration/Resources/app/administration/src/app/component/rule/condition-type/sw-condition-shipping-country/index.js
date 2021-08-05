import template from './sw-condition-shipping-country.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the ShippingCountryRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-country :condition="condition" :level="0"></sw-condition-shipping-country>
 */
Component.extend('sw-condition-shipping-country', 'sw-condition-base', {
    template,
    inject: ['repositoryFactory'],

    data() {
        return {
            shippingCountries: null,
            inputKey: 'countryIds',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('multiStore'),
            );
        },

        countryRepository() {
            return this.repositoryFactory.create('country');
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
            this.shippingCountries = new EntityCollection(
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
                this.shippingCountries = countries;
            });
        },

        setCountryIds(countries) {
            this.countryIds = countries.getIds();
            this.shippingCountries = countries;
        },
    },
});
