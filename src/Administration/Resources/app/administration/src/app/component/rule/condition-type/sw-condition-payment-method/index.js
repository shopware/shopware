import template from './sw-condition-payment-method.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the PaymentMethodRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-payment-method :condition="condition" :level="0"></sw-condition-payment-method>
 */
Component.extend('sw-condition-payment-method', 'sw-condition-base', {
    template,
    inject: ['repositoryFactory', 'feature'],

    data() {
        return {
            paymentMethods: null,
        };
    },

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('multiStore');
        },

        paymentMethodRepository() {
            return this.repositoryFactory.create('payment_method');
        },

        paymentMethodIds: {
            get() {
                this.ensureValueExist();
                return this.condition.value.paymentMethodIds || [];
            },
            set(paymentMethodIds) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, paymentMethodIds };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.paymentMethodIds']),

        currentError() {
            return this.conditionValueOperatorError || this.conditionValuePaymentMethodIdsError;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.paymentMethods = new EntityCollection(
                this.paymentMethodRepository.route,
                this.paymentMethodRepository.entityName,
                Context.api,
            );

            if (this.paymentMethodIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.paymentMethodIds);

            return this.paymentMethodRepository.search(criteria, Context.api).then((paymentMethods) => {
                this.paymentMethods = paymentMethods;
            });
        },

        setPaymentMethodIds(paymentMethods) {
            this.paymentMethodIds = paymentMethods.getIds();
            this.paymentMethods = paymentMethods;
        },
    },
});
