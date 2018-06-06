import { Component, Mixin } from 'src/core/shopware';
import template from './sw-order-detail.html.twig';
import './sw-order-detail.less';

Component.register('sw-order-detail', {
    template,

    mixins: [
        Mixin.getByName('order')
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        onSave() {
            this.saveProduct();
        },

        createdComponent() {
            if (this.$route.params.id) {
                this.orderId = this.$route.params.id;
            }
        }
    }
});
