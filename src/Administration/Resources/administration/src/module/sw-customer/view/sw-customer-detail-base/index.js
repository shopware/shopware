import { Component } from 'src/core/shopware';
import template from './sw-customer-detail-base.html.twig';
import './sw-customer-detail-base.less';

Component.register('sw-customer-detail-base', {
    template,

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
        isLoading: {
            type: Boolean,
            required: true,
            default: false
        },
        customerName: {
            type: String,
            required: true,
            default: ''
        },
        applications: {
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

    methods: {
        onActivateCustomerEditMode() {
            this.$emit('activateCustomerEditMode');
        }
    },

    computed: {
        editMode() {
            return this.customerEditMode;
        }
    }
});
