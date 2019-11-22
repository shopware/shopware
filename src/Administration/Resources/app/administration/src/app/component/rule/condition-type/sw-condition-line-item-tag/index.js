import template from './sw-condition-line-item-tag.html.twig';

const { Component, Context } = Shopware;
const { mapApiErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the LineItemTagRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item-tag :condition="condition" :level="0"></sw-condition-line-item-tag>
 */
Component.extend('sw-condition-line-item-tag', 'sw-condition-base', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            tags: null
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
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
            }
        },

        ...mapApiErrors('condition', ['value.operator', 'value.identifiers']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueIdentifiersError;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.tags = new EntityCollection(
                this.tagRepository.route,
                this.tagRepository.entityName,
                Context.api
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
        }
    }
});
