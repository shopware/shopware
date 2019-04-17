import { Component, State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-product-create.html.twig';

Component.extend('sw-product-create', 'sw-product-detail', {
    template,

    inject: ['numberRangeService'],

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.product.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    data() {
        return {
            productNumberPreview: ''
        };
    },

    computed: {
        languageStore() {
            return State.getStore('language');
        }
    },

    methods: {
        createdComponent() {
            if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
                this.languageStore.setCurrentId(this.languageStore.systemLanguageId);
            }

            if (this.$route.params.id) {
                this.productStore.create(this.$route.params.id);
            }

            this.$super.createdComponent();

            this.product.price.linked = true;

            this.numberRangeService.reserve('product', '', true).then((response) => {
                this.productNumberPreview = response.number;
                this.product.productNumber = response.number;
            });
        },

        onSave() {
            if (this.productNumberPreview === this.product.productNumber) {
                this.numberRangeService.reserve('product').then((response) => {
                    this.productNumberPreview = 'reserved';
                    this.product.productNumber = response.number;
                    this.$super.onSave().then(() => {
                        this.$router.push({ name: 'sw.product.detail', params: { id: this.product.id } });
                    });
                });
            } else {
                this.$super.onSave().then(() => {
                    this.$router.push({ name: 'sw.product.detail', params: { id: this.product.id } });
                });
            }
        }
    }
});
