import template from './sw-condition-line-item-property.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the LineItemPropertyRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-property :condition="condition" :level="0"></sw-condition-line-item-property>
 */
Component.extend('sw-condition-line-item-property', 'sw-condition-base', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            options: null
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
            }
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.identifiers']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueIdentifiersError;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.options = new EntityCollection(
                this.optionRepository.route,
                this.optionRepository.entityName,
                Context.api
            );

            if (this.identifiers.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.identifiers);

            return this.optionRepository.search(criteria, Context.api).then((options) => {
                this.options = options;
            });
        },

        setOptions(options) {
            this.identifiers = options.getIds();
            this.options = options;
        }
    }
});
