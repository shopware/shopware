import template from './sw-sales-channel-detail-google-categories.html.twig';
import './sw-sales-channel-detail-google-categories.scss';

const { Component } = Shopware;

Component.register('sw-sales-channel-detail-google-categories', {
    template,

    props: {
        selectedShopCategory: {
            type: Object,
            required: true,
            default: null
        }
    },

    data() {
        return {
            selectedGoogleCategory: ''
        };
    },

    watch: {
        selectedGoogleCategory: {
            handler() {
                this.$emit('on-change-google-category', {
                    selectedShopCategory: this.selectedShopCategory,
                    selectedGoogleCategory: this.selectedGoogleCategory
                });
            }
        }
    }
});
