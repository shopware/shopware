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
        categoryService() {
            return this.categoryService;
        },

        categoriesStore() {
            return this.product.getAssociationStore('categories');
        }
    },

    methods: {
        onInputCategories(items) {
            this.categoriesStore.removeAll();

            items.forEach((item) => {
                this.categoriesStore.addRelationship(item);
            });
        }
    }
});
