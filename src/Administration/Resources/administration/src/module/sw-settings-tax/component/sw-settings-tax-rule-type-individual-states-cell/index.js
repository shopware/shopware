import template from './sw-settings-tax-rule-type-individual-states-cell.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-tax-rule-type-individual-states-cell', {
    template,

    inject: ['repositoryFactory', 'apiContext'],

    props: {
        taxRule: {
            type: Object,
            required: true
        }
    },

    computed: {
        stateRepository() {
            return this.repositoryFactory.create('country_state');
        }
    },

    watch: {
        'taxRule.data.states'() {
            this.loadStates();
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
            this.loadStates();
        },
        loadStates() {
            if (!this.taxRule.data
                || !this.taxRule.data.states
                || !this.taxRule.data.states.length
            ) {
                return;
            }

            const criteria = new Criteria();
            criteria.setIds(this.taxRule.data.states);

            this.stateRepository.search(criteria, this.apiContext).then(states => {
                this.individualStates = states.map(state => state.name);
            });
        }
    }
});
