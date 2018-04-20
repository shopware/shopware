import { Component, Mixin } from 'src/core/shopware';
import template from './sw-customer-detail.html.twig';
import './sw-customer-detail.less';

Component.register('sw-customer-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('customer')
    ],

    beforeRouteLeave(to, from, next) {
        this.$store.commit('customer/setEditMode', false);
        next();
    },

    created() {
        if (this.$route.params.id) {
            this.customerId = this.$route.params.id;
        }
    },

    methods: {
        onSave() {
            this.saveCustomer();
            this.$store.commit('customer/setEditMode', false);
        },

        onAbort() {
            this.$store.commit('customer/setEditMode', false);
        }
    }
});
