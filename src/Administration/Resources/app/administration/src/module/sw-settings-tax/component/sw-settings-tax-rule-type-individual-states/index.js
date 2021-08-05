import template from './sw-settings-tax-rule-type-individual-states.html.twig';

const { Component, Context } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

Component.register('sw-settings-tax-rule-type-individual-states', {
    template,

    inject: ['repositoryFactory'],

    props: {
        taxRule: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            individualStates: null,
        };
    },

    computed: {
        exclusionCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('countryId', this.taxRule.countryId));

            return criteria;
        },
        stateRepository() {
            return this.repositoryFactory.create('country_state');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.taxRule.data
                || !this.taxRule.data.states
                || !this.taxRule.data.states.length
            ) {
                this.taxRule.data = { states: [] };
                this.individualStates = new EntityCollection(
                    this.stateRepository.route,
                    this.stateRepository.entityName,
                    Context.api,
                );
            } else {
                const criteria = new Criteria();
                criteria.setIds(this.taxRule.data.states);

                this.stateRepository.search(criteria, Context.api).then(collection => {
                    this.individualStates = collection;
                });
            }
        },

        onChange(collection) {
            this.individualStates = collection;
            this.taxRule.data.states = collection.getIds();
        },
    },
});
