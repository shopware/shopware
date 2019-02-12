import { Component, State } from 'src/core/shopware';
import template from './sw-product-category-form.html.twig';

Component.register('sw-product-category-form', {
    template,

    props: {
        product: {
            type: Object,
            required: true,
            default: {}
        }
    },

    computed: {
        categoryStore() {
            return State.getStore('category');
        },

        categoryAssociationStore() {
            return this.product.getAssociation('categories');
        }
    }
});
