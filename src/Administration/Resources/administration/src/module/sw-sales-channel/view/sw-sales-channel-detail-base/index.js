import { Component, Mixin, State } from 'src/core/shopware';
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

    computed: {
        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        },

        catalogStore() {
            return State.getStore('catalog');
        },

        countryStore() {
            return State.getStore('country');
        },

        currencyStore() {
            return State.getStore('currency');
        },

        languageStore() {
            return State.getStore('language');
        },

        paymentMethodStore() {
            return State.getStore('payment_method');
        },

        shippingMethodStore() {
            return State.getStore('shipping_method');
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        'salesChannel.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
        },

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
        }
    }
});
