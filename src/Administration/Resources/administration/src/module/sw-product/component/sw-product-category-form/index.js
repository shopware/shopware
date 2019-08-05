import { Component } from 'src/core/shopware';
import { mapApiErrors } from 'src/app/service/map-errors.service';
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

        ...mapApiErrors('product', ['tags']),

        hasSelectedVisibilities() {
            if (this.product && this.product.visibilities) {
                return this.product.visibilities.length > 0;
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
