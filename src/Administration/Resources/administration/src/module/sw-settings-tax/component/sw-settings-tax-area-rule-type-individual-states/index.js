import template from './sw-settings-tax-area-rule-type-individual-states.html.twig';

const { Component } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

Component.register('sw-settings-tax-area-rule-type-individual-states', {
    template,

    inject: ['repositoryFactory', 'apiContext'],

    props: {
        taxAreaRule: {
            type: Object,
            required: true
        }
    },

    computed: {
        exclusionCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('countryId', this.taxAreaRule.countryId));

            return criteria;
        },
        stateRepository() {
            return this.repositoryFactory.create('country_state');
        }
    },

    data() {
        return {
            individualStates: null
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.taxAreaRule.data
                || !this.taxAreaRule.data.states
                || !this.taxAreaRule.data.states.length
            ) {
                this.taxAreaRule.data = { states: [] };
                this.individualStates = new EntityCollection(
                    this.stateRepository.route,
                    this.stateRepository.entityName,
                    this.apiContext
                );
            } else {
                const criteria = new Criteria();
                criteria.addFilter(Criteria.equalsAny('id', this.taxAreaRule.data.states));

                this.stateRepository.search(criteria, this.apiContext).then(collection => {
                    this.individualStates = collection;
                });
            }
        },

        onChange(collection) {
            this.taxAreaRule.data.states = collection.getIds();
        }
    }
});
