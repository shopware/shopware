/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail.html.twig';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            salesChannel: null,
            isLoading: false,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },
    },

    created() {
        this.loadSalesChannel();
    },

    methods: {
        async loadSalesChannel() {
            this.isLoading = true;
            this.salesChannel = await this.salesChannelRepository.get(this.$route.params.id);
            this.isLoading = false;
        },
    },
};
