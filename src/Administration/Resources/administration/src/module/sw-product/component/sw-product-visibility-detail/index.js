import { Component } from 'src/core/shopware';
import template from './sw-product-visibility-detail.html.twig';
import './sw-product-visibility-detail.scss';

Component.register('sw-product-visibility-detail', {
    template,

    props: {
        visibilities: {
            type: Object,
            required: true,
            default: {}
        }
    },

    data() {
        return {
            items: [],
            page: 1,
            limit: 10,
            total: 0
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.onPageChange({ page: 1, limit: this.limit });
        },

        onPageChange(params) {
            const offset = (params.page - 1) * params.limit;
            const all = Object.values(this.visibilities.store).filter((item) => {
                return !item.isDeleted;
            });
            this.total = all.length;

            this.items = all.slice(offset, offset + params.limit);
        }
    }
});
