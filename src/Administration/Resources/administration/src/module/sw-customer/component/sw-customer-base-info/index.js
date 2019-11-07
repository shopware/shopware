import template from './sw-customer-base-info.html.twig';
import './sw-customer-base-info.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-customer-base-info', {
    template,

    inject: [
        'repositoryFactory',
        'apiContext'
    ],

    props: {
        customer: {
            type: Object,
            required: true
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
        language: {
            type: Object,
            required: true,
            default() {
                return {};
            }
        },
        customerEditMode: {
            type: Boolean,
            required: true,
            default: false
        },
        isLoading: {
            type: Boolean,
            required: false,
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
        orderRepository() {
            return this.repositoryFactory.create('order');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const criteria = new Criteria(1, 1);
            criteria.addAggregation(Criteria.sum('orderAmount', 'amountTotal'));
            criteria.addFilter(Criteria.equals('order.orderCustomer.customerId', this.$route.params.id));
            this.orderRepository.search(criteria, this.apiContext).then((response) => {
                this.orderCount = response.total;
                this.orderAmount = response.aggregations.orderAmount.sum;
            });
        },

        onEditCustomer() {
            this.$emit('customer-active-edit-mode');
        }
    }
});
