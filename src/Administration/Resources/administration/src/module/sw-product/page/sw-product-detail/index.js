import { Component, State } from 'src/core/shopware';
import template from './sw-product-detail.html.twig';
import './sw-product-detail.less';

Component.register('sw-product-detail', {
    template,

    data() {
        return {
            product: {},
            manufacturers: [],
            currencies: [],
            taxes: []
        };
    },

    computed: {
        productStore() {
            return State.getStore('product');
        },

        manufacturerStore() {
            return State.getStore('product_manufacturer');
        },

        currencyStore() {
            return State.getStore('currency');
        },

        taxStore() {
            return State.getStore('tax');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.productId = this.$route.params.id;
                this.product = this.productStore.getById(this.productId);

                this.product.getAssociationStore('categories').getList({
                    offset: 0,
                    limit: 50
                });

                this.manufacturerStore.getList({ offset: 0, limit: 100 }).then((response) => {
                    this.manufacturers = response.items;
                });

                this.currencyStore.getList({ offset: 0, limit: 100 }).then((response) => {
                    this.currencies = response.items;
                });

                this.taxStore.getList({ offset: 0, limit: 100 }).then((response) => {
                    this.taxes = response.items;
                });
            }
        },

        onSave() {
            this.product.save();
        }
    }
});
