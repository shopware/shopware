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
            salesChannelType: {},
            countries: [],
            shippingMethods: [],
            paymentMethods: [],
            isLoading: false
        };
    },
    computed: {
        salesChannelStore() {
            return State.getStore('sales_channel');
        },

        salesChannelTypeStore() {
            return State.getStore('sales_channel_type');
        },

        countryStore() {
            return State.getStore('country');
        },

        languageStore() {
            return State.getStore('language');
        },

        shippingMethodStore() {
            return State.getStore('shipping_method');
        },

        paymentMethodStore() {
            return State.getStore('payment_method');
        },

        currencyStore() {
            return State.getStore('currency');
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

            this.countryStore.getList({ offset: 0, limit: 100 }).then((response) => {
                this.countries = response.items;
            });

            this.shippingMethodStore.getList({ offset: 0, limit: 100 }).then((response) => {
                this.shippingMethods = response.items;
            });

            this.paymentMethodStore.getList({ offset: 0, limit: 100 }).then((response) => {
                this.paymentMethods = response.items;
            });
        },

        onSave() {
            const titleSaveSuccess = this.$tc('sw-sales-channel.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-sales-channel.detail.messageSaveSuccess',
                0,
                { name: this.salesChannel.name }
            );

            return this.salesChannel.save(true, true).then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
