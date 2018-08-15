import { Component, State } from 'src/core/shopware';
import template from './sw-sales-channel-detail-base.html.twig';
import './sw-sales-channel-detail-base.less';

Component.register('sw-sales-channel-detail-base', {
    template,

    inject: ['salesChannelService', 'currencyService', 'languageService', 'catalogService'],

    props: {
        salesChannel: {
            type: Object,
            required: true,
            default: {}
        },
        countries: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        shippingMethods: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        },
        paymentMethods: {
            type: Array,
            required: true,
            default() {
                return [];
            }
        }
    },

    data() {
        return {
            showSecretAccessKey: false,
            isLoadingAPICard: false
        };
    },

    computed: {
        secretAccessKeyFieldType() {
            return this.showSecretAccessKey ? 'text' : 'password';
        },

        catalogStore() {
            return State.getStore('catalog');
        },

        currencyStore() {
            return State.getStore('currency');
        },

        languageStore() {
            return State.getStore('language');
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
            this.isLoadingAPICard = true;

            this.salesChannelService.generateKey().then((response) => {
                this.salesChannel.accessKey = response.accessKey;
                this.salesChannel.secretAccessKey = response.secretAccessKey;
                this.showSecretAccessKey = true;
                this.isLoadingAPICard = false;
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
        }
    }
});
