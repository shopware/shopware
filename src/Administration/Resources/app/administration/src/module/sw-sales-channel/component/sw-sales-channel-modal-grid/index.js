import template from './sw-sales-channel-modal-grid.html.twig';
import './sw-sales-channel-modal-grid.scss';

const { Component, StateDeprecated } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-sales-channel-modal-grid', {
    template,

    inject: { repositoryFactory: 'repositoryFactory' },

    data() {
        return {
            salesChannelTypes: [],
            isLoading: false,
            total: 0,
            productStreamsExist: false,
            productStreamsLoading: false
        };
    },

    computed: {
        salesChannelTypeStore() {
            return StateDeprecated.getStore('sales_channel_type');
        },

        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const params = {
                limit: 500,
                page: 1
            };

            this.isLoading = true;

            this.salesChannelTypeStore.getList(params).then((response) => {
                this.total = response.total;
                this.salesChannelTypes = response.items;
                this.isLoading = false;
            });

            this.productStreamsLoading = true;
            this.productStreamRepository.search(new Criteria(1, 1), Shopware.Context.api).then((result) => {
                if (result.total > 0) {
                    this.productStreamsExist = true;
                }
                this.productStreamsLoading = false;
            });
        },

        onAddChannel(id) {
            this.$emit('grid-channel-add', id);
        },

        onOpenDetail(id) {
            const detailType = this.salesChannelTypes.find(a => a.id === id);
            this.$emit('grid-detail-open', detailType);
        },

        isProductComparisonSalesChannelType(salesChannelTypeId) {
            return salesChannelTypeId === 'ed535e5722134ac1aa6524f73e26881b';
        }
    }
});
