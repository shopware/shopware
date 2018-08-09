import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-sales-channel-detail.html.twig';

Component.register('sw-sales-channel-detail', {

    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            salesChannel: {},
            salesChannelCurrencies: [],
            salesChannelType: {},
            countries: [],
            currencies: [],
            languages: [],
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

            const params = {
                limit: 1,
                offset: 0,
                criteria: CriteriaFactory.term('id', this.$route.params.id)
            };

            // todo change everything when salesChannel relations are done by API

            this.currencyStore.getList({ offset: 0, limit: 100 }).then((response) => {
                this.currencies = response.items;

                this.salesChannelStore.getList(params).then((resp) => {
                    this.salesChannel = resp.items[0];

                    // because of getList...
                    // todo only user getById when relations are done
                    if (this.salesChannel === undefined) {
                        this.salesChannel = this.salesChannelStore.getById(this.$route.params.id);
                    }

                    this.salesChannel.currencyIds.forEach((id) => {
                        this.salesChannelCurrencies.push(this.currencies.find(a => a.id === id));
                    });
                });
            });

            this.countryStore.getList({ offset: 0, limit: 100 }).then((response) => {
                this.countries = response.items;
            });

            this.languageStore.getList({ offset: 0, limit: 100 }).then((response) => {
                this.lanuages = response.items;
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
