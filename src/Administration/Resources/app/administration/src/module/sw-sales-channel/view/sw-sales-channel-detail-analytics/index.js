import template from './sw-sales-channel-detail-analytics.html.twig';

import './sw-sales-channel-detail-analytics.scss';

const { Component, Context } = Shopware;

Component.register('sw-sales-channel-detail-analytics', {
    template,

    inject: [
        'repositoryFactory'
    ],

    props: {
        isLoading: {
            type: Boolean,
            default: false
        },
        salesChannel: {
            required: true
        }
    },

    watch: {
        salesChannel() {
            this.createAnalyticsData();
        }
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
        }
    }
});
