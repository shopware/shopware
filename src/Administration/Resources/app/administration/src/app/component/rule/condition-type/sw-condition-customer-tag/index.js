import template from './sw-condition-customer-tag.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the CustomerTagRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-customer-tag :condition="condition" :level="0"></sw-condition-customer-tag>
 */
Component.extend('sw-condition-customer-tag', 'sw-condition-base', {
    template,
    inheritAttrs: false,

    inject: ['repositoryFactory'],

    data() {
        return {
            tags: null,
            inputKey: 'identifiers',
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.addEmptyOperatorToOperatorSet(
                this.conditionDataProviderService.getOperatorSet('multiStore'),
            );
        },

        tagRepository() {
            return this.repositoryFactory.create('tag');
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
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.tags = new EntityCollection(
                this.tagRepository.route,
                this.tagRepository.entityName,
                Context.api,
            );

            if (this.identifiers.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.identifiers);

            return this.tagRepository.search(criteria, Context.api).then((tags) => {
                this.tags = tags;
            });
        },

        setTags(tags) {
            this.identifiers = tags.getIds();
            this.tags = tags;
        },
    },
});
