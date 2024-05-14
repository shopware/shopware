/**
 * @package buyers-experience
 */

import template from './sw-sales-channel-detail-analytics.html.twig';

import './sw-sales-channel-detail-analytics.scss';

const { Context } = Shopware;

/**
 * @private
 * @package buyers-experience
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    props: {
        isLoading: {
            type: Boolean,
            default: false,
        },

        // eslint-disable-next-line vue/require-prop-types
        salesChannel: {
            required: true,
        },
    },

    watch: {
        salesChannel() {
            this.createAnalyticsData();
        },
    },

    created() {
        this.createAnalyticsData();
    },

    methods: {
        createAnalyticsData() {
            if (this.salesChannel && !this.salesChannel.analytics) {
                const repository = this.repositoryFactory.create('sales_channel_analytics');
                this.salesChannel.analytics = repository.create(Context.api);
            }
        },
    },
};
