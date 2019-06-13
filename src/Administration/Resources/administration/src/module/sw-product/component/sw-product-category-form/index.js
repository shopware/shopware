import { Component } from 'src/core/shopware';
import { mapState } from 'vuex';
import template from './sw-product-category-form.html.twig';

Component.register('sw-product-category-form', {
    template,

    inject: ['repositoryFactory', 'context'],

    data() {
        return {
            displayVisibilityDetail: false,
            multiSelectVisible: true
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'localMode',
            'loading'
        ]),

        categoriesCollection() {
            return !this.loading.product ? this.product.categories : {};
        }
    },

    methods: {
        displayAdvancedVisibility() {
            this.displayVisibilityDetail = true;
        },

        closeAdvancedVisibility() {
            this.displayVisibilityDetail = false;
        }
    }
});
