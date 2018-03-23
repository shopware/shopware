import { Component } from 'src/core/shopware';
import template from './sw-product-basic-form.html.twig';

Component.register('sw-product-basic-form', {
    template,

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
        serviceProvider: {
            type: Object,
            required: true
        }
    },

    computed: {
        productCategoryIds() {
            return this.product.categories.map((category) => {
                return { id: category.id };
            });
        }
    }
});
