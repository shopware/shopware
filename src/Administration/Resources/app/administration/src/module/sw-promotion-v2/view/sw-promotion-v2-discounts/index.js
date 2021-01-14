import template from './sw-promotion-v2-discounts.html.twig';
import './sw-promotion-v2-discounts.scss';

const { Component } = Shopware;

Component.register('sw-promotion-v2-discounts', {
    template,

    data() {
        return {
            isActive: false,
            selectedDiscountType: null,
            showDiscountModal: false
        };
    },

    methods: {
        onButtonClick() {
            this.isActive = !this.isActive;
        },

        onChangeSelection(value) {
            this.selectedDiscountType = value;
        },

        onShowDiscountModal() {
            this.showDiscountModal = true;
        },

        onCloseDiscountModal() {
            this.selectedDiscountType = null;
            this.showDiscountModal = false;
        }
    }
});
