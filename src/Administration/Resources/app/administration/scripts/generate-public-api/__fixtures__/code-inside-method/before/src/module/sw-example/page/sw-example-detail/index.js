/**
 * @sw-package framework
 */
import template from './sw-example-detail.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            salesChannels: null,
            isLoading: false,
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadSalesChannels();
        },

        async loadSalesChannels() {
            this.isLoading = true;

            try {
                this.salesChannels = await this.salesChannelRepository.search(this.salesChannelCriteria);
            } finally {
                this.isLoading = false;
            }
        },

        onChangeSalesChannels(salesChannels) {
            this.salesChannels = salesChannels;
        },
    },
};
