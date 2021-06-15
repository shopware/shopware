import template from './sw-condition-line-items-in-cart.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the LineItemsInCartRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-items-in-cart :condition="condition" :level="0"></sw-condition-line-items-in-cart>
 */
Component.extend('sw-condition-line-items-in-cart', 'sw-condition-base', {
    template,
    inject: ['repositoryFactory'],

    data() {
        return {
            products: null,
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        productRepository() {
            return this.repositoryFactory.create('product');
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

        productCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('options.group');

            return criteria;
        },

        productContext() {
            return { ...Shopware.Context.api, inheritance: true };
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.products = new EntityCollection(
                this.productRepository.route,
                this.productRepository.entityName,
                Context.api,
            );

            if (this.identifiers.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.addAssociation('options.group');
            criteria.setIds(this.identifiers);

            return this.productRepository.search(criteria, this.productContext).then((products) => {
                this.products = products;
            });
        },

        setProductIds(products) {
            this.identifiers = products.getIds();
            this.products = products;
        },
    },
});
