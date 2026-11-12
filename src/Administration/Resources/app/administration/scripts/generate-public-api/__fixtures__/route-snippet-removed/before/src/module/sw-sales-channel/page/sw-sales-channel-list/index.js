/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-list.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            salesChannels: [],
            isLoading: false,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },
    },

    created() {
        this.getList();
    },

    methods: {
        async getList() {
            this.isLoading = true;
            this.salesChannels = await this.salesChannelRepository.search(new Criteria(1, 25));
            this.isLoading = false;
        },
    },
};
