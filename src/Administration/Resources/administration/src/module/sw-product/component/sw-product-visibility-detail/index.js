import { Component } from 'src/core/shopware';
import { mapState } from 'vuex';
import template from './sw-product-visibility-detail.html.twig';
import './sw-product-visibility-detail.scss';

Component.register('sw-product-visibility-detail', {
    template,

    data() {
        return {
            items: [],
            page: 1,
            limit: 10,
            total: 0
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product'
        ])
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.$store.dispatch('swProductDetail/loadProduct').then(() => {
                this.onPageChange({ page: 1, limit: this.limit });
            });
        },

        onPageChange(params) {
            const offset = (params.page - 1) * params.limit;
            const all = Object.values(this.product.visibilities.items).filter((item) => {
                return !item.isDeleted;
            });
            this.total = all.length;

            this.items = all.slice(offset, offset + params.limit);
        }
    }
});
