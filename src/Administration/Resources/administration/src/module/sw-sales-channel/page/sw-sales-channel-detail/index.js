import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-sales-channel-detail.html.twig';

Component.register('sw-sales-channel-detail', {

    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            salesChannel: {},
            isLoading: false
        };
    },
    computed: {
        salesChannelStore() {
            return State.getStore('sales_channel');
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.id) {
                return;
            }

            this.salesChannel = this.salesChannelStore.getById(this.$route.params.id);

            this.salesChannel.getAssociationStore('catalogs').getList({
                offset: 0,
                limit: 50
            });

            this.salesChannel.getAssociationStore('languages').getList({
                offset: 0,
                limit: 50
            });

            this.salesChannel.getAssociationStore('currencies').getList({
                offset: 0,
                limit: 50
            });

            this.salesChannel.getAssociationStore('countries').getList({
                offset: 0,
                limit: 50
            });

            this.salesChannel.getAssociationStore('shippingMethods').getList({
                offset: 0,
                limit: 50
            });

            this.salesChannel.getAssociationStore('paymentMethods').getList({
                offset: 0,
                limit: 50
            });
        },

        onSave() {
            const titleSaveSuccess = this.$tc('sw-sales-channel.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-sales-channel.detail.messageSaveSuccess',
                0,
                { name: this.salesChannel.name }
            );

            return this.salesChannel.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
