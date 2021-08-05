import template from './sw-category-sales-channel-card.html.twig';
import './sw-category-sales-channel-card.scss';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.5.0 - will be removed without replacement
 */
Component.register('sw-category-sales-channel-card', {
    template,

    props: {
        category: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        navigationSalesChannels() {
            return this.category.navigationSalesChannels;
        },

        serviceSalesChannels() {
            return this.category.serviceSalesChannels;
        },

        footerSalesChannels() {
            return this.category.footerSalesChannels;
        },
    },
});
