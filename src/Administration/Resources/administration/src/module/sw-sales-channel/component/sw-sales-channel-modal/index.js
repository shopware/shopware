import template from './sw-sales-channel-modal.html.twig';
import './sw-sales-channel-modal.scss';

const { Component, State } = Shopware;

Component.register('sw-sales-channel-modal', {
    template,

    inject: ['salesChannelService'],

    data() {
        return {
            salesChannelTypes: [],
            isLoading: false,
            detailType: false
        };
    },

    computed: {
        salesChannelStore() {
            return State.getStore('sales_channel');
        },

        salesChannelTypeStore() {
            return State.getStore('sales_channel_type');
        },

        modalTitle() {
            if (this.detailType) {
                return `${this.$tc('sw-sales-channel.modal.titleDetailPrefix')} ${this.detailType.name}`;
            }

            return this.$tc('sw-sales-channel.modal.title');
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

        onCloseModal() {
            this.$emit('modal-close');
        },

        onOpenDetail(id) {
            this.detailType = this.salesChannelTypes.find(a => a.id === id);
        },

        onAddChannel(id) {
            this.onCloseModal();

            if (id) {
                this.$router.push({ name: 'sw.sales.channel.create', params: { typeId: id } });
            }
        }
    }
});
