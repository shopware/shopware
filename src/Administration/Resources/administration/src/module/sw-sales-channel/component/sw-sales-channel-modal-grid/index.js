import template from './sw-sales-channel-modal-grid.html.twig';
import './sw-sales-channel-modal-grid.scss';

const { Component, StateDeprecated } = Shopware;

Component.register('sw-sales-channel-modal-grid', {
    template,

    data() {
        return {
            salesChannelTypes: [],
            isLoading: false,
            total: 0
        };
    },

    computed: {
        salesChannelTypeStore() {
            return StateDeprecated.getStore('sales_channel_type');
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
        },

        onAddChannel(id) {
            this.$emit('grid-channel-add', id);
        },

        onOpenDetail(id) {
            const detailType = this.salesChannelTypes.find(a => a.id === id);
            this.$emit('grid-detail-open', detailType);
        }
    }
});
