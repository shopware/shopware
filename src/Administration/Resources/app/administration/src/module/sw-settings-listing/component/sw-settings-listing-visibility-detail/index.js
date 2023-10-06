import template from './sw-settings-listing-visibility-detail.html.twig';

const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        config: {
            required: true,
            type: Array,
        },
    },

    data() {
        return {
            items: [],
            page: 1,
            limit: 10,
            total: 0,
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
            this.onPageChange({ page: this.page, limit: this.limit });
        },

        onPageChange(params) {
            const offset = (params.page - 1) * params.limit;
            this.total = this.config.length;

            this.fetchSalesChannels().then(config => {
                this.items = config.slice(offset, offset + params.limit);
            });
        },

        changeVisibilityValue(event, item) {
            item.visibility = Number(event);
        },

        fetchSalesChannels() {
            const salesChannelIds = this.config.map(config => config.id);
            const criteria = new Criteria(1, 25);

            criteria.addFilter(Criteria.equalsAny('id', salesChannelIds));

            return this.salesChannelRepository.search(criteria).then(salesChannels => {
                return this.config.map(config => {
                    const salesChannel = salesChannels.get(config.id);
                    if (!salesChannel) {
                        return config;
                    }

                    return {
                        ...config,
                        name: salesChannel.name,
                    };
                });
            });
        },
    },
};
