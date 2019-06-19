import { Component } from 'src/core/shopware';
import { mapState, mapGetters } from 'vuex';
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
            'parentProduct',
            'localMode',
            'loading'
        ]),

        ...mapGetters('swProductDetail', [
            'isChild'
        ]),

        hasVisibilitesSelected() {
            if (this.product && this.product.visibilities) {
                return Object.values(this.product.visibilities.items).length > 0;
            }
            return false;
        }
    },

    methods: {
        displayAdvancedVisibility() {
            this.displayVisibilityDetail = true;
        },

        closeAdvancedVisibility() {
            this.displayVisibilityDetail = false;
        },

        reloadProductVisibility() {
            this.$nextTick(() => {
                this.$refs.productVisibility.reloadVisibleItems();
            });
        }
    }
});
