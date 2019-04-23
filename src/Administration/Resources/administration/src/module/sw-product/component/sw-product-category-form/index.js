import { Component } from 'src/core/shopware';
import { mapState } from 'vuex';
import template from './sw-product-category-form.html.twig';

Component.register('sw-product-category-form', {
    template,

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
        ])
    },

    methods: {
        displayAdvancedVisibility() {
            this.displayVisibilityDetail = true;
        },

        closeAdvancedVisibility() {
            this.displayVisibilityDetail = false;
        },

        reloadVisibilites() {
            this.$store.dispatch('swProductDetail/loadProduct');
        }
    }
});
