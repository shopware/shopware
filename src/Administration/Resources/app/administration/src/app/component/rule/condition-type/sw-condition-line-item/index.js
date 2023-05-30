import template from './sw-condition-line-item.html.twig';
import './sw-condition-line-item.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @package business-ops
 * @description Condition for the LineItemRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item :condition="condition" :level="0"></sw-condition-line-item>
 */
Component.extend('sw-condition-line-item', 'sw-condition-base-line-item', {
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

        productIds: {
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
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');

            return criteria;
        },

        resultCriteria() {
            const criteria = new Criteria(1, 25);
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
                this.productContext,
            );


            if (this.productIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria(1, 25);
            criteria.addAssociation('options.group');
            criteria.setIds(this.productIds);

            return this.productRepository.search(criteria, this.productContext).then((products) => {
                this.products = products;
            });
        },

        setIds(productCollection) {
            this.productIds = productCollection.getIds();
            this.products = productCollection;
        },
    },
});
