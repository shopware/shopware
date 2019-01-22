import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-sales-channel-detail.html.twig';

Component.register('sw-sales-channel-detail', {

    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('salesChannel')
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
        },
        salesChannelLanguagesStore() {
            return this.salesChannel.getAssociation('languages');
        },
        salesChannelCurrenciesStore() {
            return this.salesChannel.getAssociation('currencies');
        },
        isStoreFront() {
            return this.salesChannel.typeId === '8a243080f92e4c719546314b577cf82b';
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

            this.salesChannel.getAssociation('catalogs').getList({
                page: 1,
                limit: 50
            });

            this.salesChannelLanguagesStore.getList({
                page: 1,
                limit: 50
            });

            this.salesChannelCurrenciesStore.getList({
                page: 1,
                limit: 50
            });

            this.salesChannel.getAssociation('countries').getList({
                page: 1,
                limit: 50
            });

            this.salesChannel.getAssociation('shippingMethods').getList({
                page: 1,
                limit: 50
            });

            this.salesChannel.getAssociation('paymentMethods').getList({
                page: 1,
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

            this.syncWithDomains();

            return this.salesChannel.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        },
        /**
         * For storefront sales channels, the possible languages and currencies are determined by those in the domains
         * instead of salesChannel.`languages`/`currencies`. Theses mappings are still required by the backend, so we
         * need to add the missing ones, that are only set in the domains.
         */
        syncWithDomains() {
            if (!this.next1387 || !this.isStoreFront || !this.salesChannel.domains) {
                return;
            }
            this.salesChannel.domains.forEach((domain) => {
                if (!this.salesChannel.languages.find(d => d.languageId === domain.languageId)) {
                    const language = this.salesChannelLanguagesStore.create(domain.languageId);
                    this.salesChannel.languages.push(language);
                    if (!this.salesChannel.languageId) {
                        this.salesChannel.languageId = language.id;
                    }
                }

                if (!this.salesChannel.currencies.find(d => d.currencyId === domain.currencyId)) {
                    const currency = this.salesChannelCurrenciesStore.create(domain.currencyId);
                    this.salesChannel.currencies.push(currency);
                    if (!this.salesChannel.currencyId) {
                        this.salesChannel.currencyId = currency.id;
                    }
                }
            });
        }
    }
});
