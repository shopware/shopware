import template from './sw-condition-shipping-method.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the ShippingMethodRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-shipping-method :condition="condition" :level="0"></sw-condition-shipping-method>
 */
Component.extend('sw-condition-shipping-method', 'sw-condition-base', {
    template,
    inject: ['repositoryFactory'],

    data() {
        return {
            shippingMethods: null,
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        shippingMethodRepository() {
            return this.repositoryFactory.create('shipping_method');
        },

        shippingMethodIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.shippingMethodIds || [];
            },
            set(shippingMethodIds) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, shippingMethodIds };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.shippingMethodIds']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValueShippingMethodIdsError;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.shippingMethods = new EntityCollection(
                this.shippingMethodRepository.route,
                this.shippingMethodRepository.entityName,
                Context.api,
            );

            if (this.shippingMethodIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.shippingMethodIds);

            return this.shippingMethodRepository.search(criteria, Context.api).then((shippingMethods) => {
                this.shippingMethods = shippingMethods;
            });
        },

        setShippingMethodIds(shippingMethods) {
            this.shippingMethodIds = shippingMethods.getIds();
            this.shippingMethods = shippingMethods;
        },
    },
});
