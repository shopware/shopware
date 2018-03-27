import { Component } from 'src/core/shopware';
import template from './sw-product-category-form.html.twig';

Component.register('sw-product-category-form', {
    template,

    inject: ['categoryService'],

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        }
    },

    computed: {
        productCategoryIds() {
            return this.product.categories.map((category) => {
                return { id: category.id };
            });
        },

        categoryService() {
            return this.categoryService;
        }
    }
});
