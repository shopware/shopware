import template from './sw-customer-base-form.html.twig';
import './sw-customer-base-form.scss';
import errorConfig from '../../error-config.json';

const { Component, Defaults } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const { Criteria } = Shopware.Data;

Component.register('sw-customer-base-form', {
    template,

    inject: ['feature'],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors('customer', errorConfig['sw.customer.detail.base'].customer),

        salutationCriteria() {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.not('or', [
                Criteria.equals('id', Defaults.defaultSalutationId),
            ]));

            return criteria;
        },
    },

    watch: {
        'customer.guest'(newVal) {
            if (newVal) {
                this.customer.password = null;
            }
        },
    },

    methods: {
        onSalesChannelChange(salesChannelId) {
            this.$emit('sales-channel-change', salesChannelId);
        },
    },
});
