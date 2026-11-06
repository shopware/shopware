/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail.html.twig';

const { Context } = Shopware;
const { Criteria } = Shopware.Data;

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
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadSalesChannel();
        },

        loadSalesChannel() {
            this.isLoading = true;
            this.salesChannelRepository
                .get(this.$route.params.id.toLowerCase(), Context.api, this.getLoadSalesChannelCriteria())
                .then((entity) => {
                    this.salesChannel = entity;
                    this.isLoading = false;
                });
        },

        getLoadSalesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addAssociation('domains');

            return criteria;
        },
    },
};
