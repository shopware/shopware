import { Component, State } from 'src/core/shopware';
import template from './sw-customer-base-form.html.twig';

Component.register('sw-customer-base-form', {
    template,

    inject: ['swCustomerCreateOnChangeSalesChannel'],

    props: {
        customer: {
            type: Object,
            required: true
        },
        salesChannels: {
            type: Array,
            required: true,
            default() {
                return [];
            }
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
        }
    },

    data() {
        return {
            salutations: null
        };
    },

    computed: {
        salutationStore() {
            return State.getStore('salutation');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.salutationStore.getList({ page: 1, limit: 500 }).then(({ items }) => {
                this.salutations = items;
            });
        }
    }
});
