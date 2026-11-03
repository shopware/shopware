import template from './sw-settings-tax-rule-type-individual-states.html.twig';
import { Criteria, EntityCollection, getApiContext, repositoryFactoryService } from '../dependencies';

/**
 * @sw-package checkout
 */

/**
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService],

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
            const criteria = new Criteria(1, 25);
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
            if (!this.taxRule.data || !this.taxRule.data.states || !this.taxRule.data.states.length) {
                this.taxRule.data = { states: [] };
                this.individualStates = new EntityCollection(
                    this.stateRepository.route,
                    this.stateRepository.entityName,
                    getApiContext(),
                );
            } else {
                const criteria = new Criteria(1, 25);
                criteria.setIds(this.taxRule.data.states);

                this.stateRepository.search(criteria, getApiContext()).then((collection) => {
                    this.individualStates = collection;
                });
            }
        },

        onChange(collection) {
            this.individualStates = collection;
            this.taxRule.data.states = collection.getIds();
        },
    },
};
