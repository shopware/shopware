import { Component } from 'src/core/shopware';
import template from './sw-customer-detail-base.html.twig';
import './sw-customer-detail-base.scss';

Component.register('sw-customer-detail-base', {
    template,

    data() {
        return {
            createMode: false
        };
    },

    props: {
        customer: {
            type: Object,
            required: true,
            default: {}
        },
        customerEditMode: {
            type: Boolean,
            required: true,
            default: false
        },
        customerName: {
            type: String,
            required: true,
            default: ''
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
        },
        languages: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        countries: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        }
    },

    computed: {
        editMode() {
            return this.customerEditMode;
        }
    },

    methods: {
        onActivateCustomerEditMode() {
            this.$emit('activateCustomerEditMode');
        }
    }
});
