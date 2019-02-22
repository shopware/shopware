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

    data() {
        return {
            displayVisibilityDetail: false
        };
    },

    computed: {
        categoryStore() {
            return State.getStore('category');
        },

        salesChannelStore() {
            return State.getStore('sales_channel');
        },

        visibilityAssociationStore() {
            return this.product.getAssociation('visibilities');
        },

        categoryAssociationStore() {
            return this.product.getAssociation('categories');
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
