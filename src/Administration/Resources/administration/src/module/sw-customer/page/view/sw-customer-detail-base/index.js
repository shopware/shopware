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
        isLoading: {
            type: Boolean,
            required: true,
            default: false
        }
    },

    methods: {
        onEditCustomer() {
            this.$store.commit('customer/setEditMode', true);
        }
    },

    computed: {
        editMode() {
            return this.$store.state.customer.editMode;
        }
    }
});
