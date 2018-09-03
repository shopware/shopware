import { Component, Mixin } from 'src/core/shopware';
import template from './sw-sales-channel-detail-base.html.twig';
import './sw-sales-channel-detail-base.less';

Component.register('sw-sales-channel-detail-base', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: [
        'salesChannelService',
        'currencyService',
        'languageService',
        'catalogService',
        'countryService',
        'shippingMethodService',
        'paymentMethodService'
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
            return this.salesChannel.getAssociation('catalogs');
        },

        countryStore() {
            return this.salesChannel.getAssociation('countries');
        },

        currencyStore() {
            return this.salesChannel.getAssociation('currencies');
        },

        languageStore() {
            return this.salesChannel.getAssociation('languages');
        },

        paymentMethodStore() {
            return this.salesChannel.getAssociation('paymentMethods');
        },

        shippingMethodStore() {
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
                }).catch(this.onCloseDeleteModal());

                this.$router.push({ name: 'sw.dashboard.index' });
            });
        }
    }
});
