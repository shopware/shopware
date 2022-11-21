import template from './sw-category-sales-channel-card.html.twig';
import './sw-category-sales-channel-card.scss';

/**
 * @deprecated tag:v6.5.0 - will be removed without replacement
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
};
