import { Component } from 'src/core/shopware';
import template from './sw-product-basic-form.html.twig';

Component.register('sw-product-basic-form', {
    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        },
        manufacturers: {
            type: Array,
            required: true,
            default: []
        },
        taxRates: {
            type: Array,
            required: true,
            default: []
        },
        serviceProvider: {
            type: Object,
            required: true
        }
    },

    computed: {
        productCategoryIds() {
            const categoryIds = [];

            this.product.categories.forEach((category) => {
                categoryIds.push({ id: category.id });
            });

            return categoryIds;
        }
    },

    template
});
