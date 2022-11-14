import template from './sw-condition-line-item-property.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @package business-ops
 * @description Condition for the LineItemPropertyRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-property :condition="condition" :level="0"></sw-condition-line-item-property>
 */
Component.extend('sw-condition-line-item-property', 'sw-condition-base-line-item', {
    template,

    inject: ['repositoryFactory', 'feature'],

    data() {
        return {
            options: null,
            searchTerm: '',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        optionRepository() {
            return this.repositoryFactory.create('property_group_option');
        },

        identifiers: {
            get() {
                this.ensureValueExist();
                return this.condition.value.identifiers || [];
            },
            set(identifiers) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, identifiers };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.identifiers']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueIdentifiersError;
        },

        optionCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.setIds(this.identifiers);
            criteria.addAssociation('group');

            if (typeof this.searchTerm === 'string' && this.searchTerm.length > 0) {
                criteria.addQuery(Criteria.contains('group.name', this.searchTerm), 500);
            }

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.options = new EntityCollection(
                this.optionRepository.route,
                this.optionRepository.entityName,
                Context.api,
                this.optionCriteria,
            );

            if (this.identifiers.length <= 0) {
                return Promise.resolve();
            }

            return this.optionRepository.search(this.optionCriteria, Context.api).then((options) => {
                this.options = options;
            });
        },

        setOptions(options) {
            this.identifiers = options.getIds();
            this.options = options;
        },

        setSearchTerm(value) {
            this.searchTerm = value;
        },

        onSelectCollapsed() {
            this.searchTerm = '';
        },
    },
});
