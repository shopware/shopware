import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-sales-channel-detail-base.html.twig';
import './sw-sales-channel-detail-base.less';

Component.register('sw-sales-channel-detail-base', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'salesChannelService'
    ],

    props: {
        salesChannel: {
            type: Object,
            required: true,
            default: {}
        }
    },

    data() {
        return {
            showDeleteModal: false
        };
    },

    computed: {
        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        },

        catalogStore() {
            return State.getStore('catalog');
        },

        catalogAssociationStore() {
            return this.salesChannel.getAssociation('catalogs');
        },

        countryStore() {
            return State.getStore('country');
        },

        countryAssociationStore() {
            return this.salesChannel.getAssociation('countries');
        },

        currencyStore() {
            return State.getStore('currency');
        },

        currencyAssociationStore() {
            return this.salesChannel.getAssociation('currencies');
        },

        languageStore() {
            return State.getStore('language');
        },

        languageAssociationStore() {
            return this.salesChannel.getAssociation('languages');
        },

        paymentMethodStore() {
            return State.getStore('payment_method');
        },

        paymentMethodAssociationStore() {
            return this.salesChannel.getAssociation('paymentMethods');
        },

        shippingMethodStore() {
            return State.getStore('shipping_method');
        },

        shippingMethodAssociationStore() {
            return this.salesChannel.getAssociation('shippingMethods');
        }
    },

    methods: {
        onGenerateKeys() {
            this.salesChannelService.generateKey().then((response) => {
                this.salesChannel.accessKey = response.accessKey;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-sales-channel.detail.titleAPIError'),
                    message: this.$tc('sw-sales-channel.detail.messageAPIError')
                });
            });
        },

        changeDefaultCurrency(id) {
            this.salesChannel.currencyId = id;
        },

        changeDefaultLanguage(id) {
            this.salesChannel.languageId = id;
        },

        changeDefaultCountry(id) {
            this.salesChannel.countryId = id;
        },

        changeDefaultPaymentMethod(id) {
            this.salesChannel.paymentMethodId = id;
        },

        changeDefaultShippingMethod(id) {
            this.salesChannel.shippingMethodId = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete() {
            this.showDeleteModal = false;
            this.$nextTick(() => {
                this.salesChannel.delete(true).then(() => {
                    this.$root.$emit('changedSalesChannels');
                });

                this.$router.push({ name: 'sw.dashboard.index' });
            });
        }
    }
});
