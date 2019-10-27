import template
    from './sw-settings-tax-area-rule-type-individual-states-cell.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-tax-area-rule-type-individual-states-cell', {
    template,

    inject: ['repositoryFactory', 'apiContext'],

    props: {
        taxAreaRule: {
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
        'taxAreaRule.data.states'() {
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
            if (!this.taxAreaRule.data
                || !this.taxAreaRule.data.states
                || !this.taxAreaRule.data.states.length
            ) {
                return;
            }

            const criteria = new Criteria();
            criteria.addFilter(Criteria.equalsAny('id', this.taxAreaRule.data.states));

            this.stateRepository.search(criteria, this.apiContext).then(states => {
                this.individualStates = states.map(state => state.name);
            });
        }
    }
});
