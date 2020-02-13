import template from './sw-condition-line-item.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the LineItemRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item :condition="condition" :level="0"></sw-condition-line-item>
 */
Component.extend('sw-condition-line-item', 'sw-condition-base', {
    template,

    inject: ['repositoryFactory'],

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productIds: {
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

    data() {
        return {
            products: null
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.products = new EntityCollection(
                this.productRepository.route,
                this.productRepository.entityName,
                Context.api
            );

            if (this.productIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.productIds);

            return this.productRepository.search(criteria, Context.api).then((products) => {
                this.products = products;
            });
        },

        setIds(productCollection) {
            this.productIds = productCollection.getIds();
            this.products = productCollection;
        }
    }
});
