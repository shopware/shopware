import template from './sw-sales-channel-modal-grid.html.twig';
import './sw-sales-channel-modal-grid.scss';

const { Component, Defaults } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-sales-channel-modal-grid', {
    template,

    inject: ['repositoryFactory'],

    props: {
        productStreamsExist: {
            type: Boolean,
            required: false,
            default: true,
        },

        productStreamsLoading: {
            type: Boolean,
            required: false,
            default: false,
        },

        addChannelAction: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            salesChannelTypes: [],
            isLoading: false,
            total: 0,
        };
    },

    computed: {
        salesChannelTypeRepository() {
            return this.repositoryFactory.create('sales_channel_type');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            const context = { ...Shopware.Context.api, languageId: Shopware.State.get('session').languageId };
            this.salesChannelTypeRepository.search(new Criteria(1, 500), context).then((response) => {
                this.total = response.total;
                this.salesChannelTypes = response;
                this.isLoading = false;
            });
        },

        onAddChannel(id) {
            this.$emit('grid-channel-add', id);
        },

        onOpenDetail(id) {
            const detailType = this.salesChannelTypes.find(salesChannelType => salesChannelType.id === id);
            this.$emit('grid-detail-open', detailType);
        },

        isProductComparisonSalesChannelType(salesChannelTypeId) {
            return salesChannelTypeId === Defaults.productComparisonTypeId;
        },
    },
});
