import template from './sw-customer-base-info.html.twig';
import './sw-customer-base-info.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-customer-base-info', {
    template,

    inject: ['repositoryFactory', 'feature'],

    props: {
        customer: {
            type: Object,
            required: true,
        },
        customerEditMode: {
            type: Boolean,
            required: true,
            default: false,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            orderAmount: 0,
            orderCount: 0,
            customerLanguage: null,
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageId() {
            return this.customer.languageId;
        },

        customerLanguageName() {
            if (this.customerLanguage) {
                return this.customerLanguage.name;
            }
            return '…';
        },

        languageCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('salesChannels.id', this.customer.salesChannelId));

            return criteria;
        },
    },

    watch: {
        languageId: {
            immediate: true,
            handler() {
                this.languageRepository.get(this.languageId).then((language) => {
                    this.customerLanguage = language;
                });
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const criteria = new Criteria(1, 1);
            criteria.addAggregation(Criteria.sum('orderAmount', 'amountTotal'));
            criteria.addFilter(Criteria.equals('order.orderCustomer.customerId', this.$route.params.id));
            this.orderRepository.search(criteria).then((response) => {
                this.orderCount = response.total;
                this.orderAmount = response.aggregations.orderAmount.sum;
            });
        },
    },
});
