import { Component, State } from 'src/core/shopware';
import template from './sw-sales-channel-modal.html.twig';
import './sw-sales-channel-modal.less';

Component.register('sw-sales-channel-modal', {
    inject: ['salesChannelService'],

    template,

    props: {
        detailTypeId: {
            type: Number,
            required: false
        }
    },

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
        getModalTitle() {
            if (this.detailType) {
                return `${this.$tc('sw-sales-channel.modal.titleDetailPrefix')} ${this.detailType.name}`;
            }

            return this.$tc('sw-sales-channel.modal.title');
        }
    },

    created() {
        const params = {
            limit: 500,
            offset: 0
        };

        this.isLoading = true;

        this.salesChannelTypeStore.getList(params).then((response) => {
            this.total = response.total;
            this.salesChannelTypes = response.items;
            this.isLoading = false;
        });
    },

    methods: {
        onCloseModal() {
            this.$emit('closeModal');
        },
        onOpenDetail(id) {
            this.detailType = this.salesChannelTypes.find(a => a.id === id);
        },
        onAddChannel(id) {
            this.onCloseModal();
            if (id) {
                this.$router.push({ name: 'sw.sales.channel.create', params: { typeId: id } });
                return;
            }

            this.$router.push({ name: 'sw.sales.channel.create', params: { typeId: this.detailType.id } });
        }
    }
});
