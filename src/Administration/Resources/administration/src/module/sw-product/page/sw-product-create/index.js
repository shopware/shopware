import { Component } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-product-create.html.twig';

Component.extend('sw-product-create', 'sw-product-detail', {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.product.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.productStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();

            this.product.price.linked = true;
        },

        onSave() {
            this.$super.onSave().then(() => {
                this.$router.push({ name: 'sw.product.detail', params: { id: this.product.id } });
            });
        }
    }
});
