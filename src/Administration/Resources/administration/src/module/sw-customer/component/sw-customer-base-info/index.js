import { Component, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-customer-base-info.html.twig';
import './sw-customer-base-info.scss';

Component.register('sw-customer-base-info', {
    template,

    props: {
        customer: {
            type: Object,
            required: true,
            default: {}
        },
        customerGroups: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        paymentMethods: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        languages: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        customerEditMode: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    data() {
        return {
            orderAmount: 0,
            orderCount: 0
        };
    },

    computed: {
        orderStore() {
            return State.getStore('order');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const aggregations = {
                orderAmount: { name: 'orderAmount', type: 'sum', field: 'amountTotal' }
            };

            const criteria = CriteriaFactory.equals('order.orderCustomer.customerId', this.customer.id);

            this.orderStore.getList({ page: 1, limit: 1, aggregations, criteria }).then((response) => {
                this.orderCount = response.total;
                this.orderAmount = response.aggregations.orderAmount[0].sum;
            });
        },

        onEditCustomer() {
            this.$emit('activateCustomerEditMode');
        }
    }
});
