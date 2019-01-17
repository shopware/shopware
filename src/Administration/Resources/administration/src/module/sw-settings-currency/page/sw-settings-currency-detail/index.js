import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-currency-detail.html.twig';

Component.register('sw-settings-currency-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('currency')
    ],

    data() {
        return {
            currency: {}
        };
    },

    computed: {
        currencyStore() {
            return State.getStore('currency');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.currencyId = this.$route.params.id;
                this.currency = this.currencyStore.getById(this.currencyId);
            }
        },

        onSave() {
            const currencyName = this.currency.name;
            const titleSaveSuccess = this.$tc('sw-settings-currency.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-currency.detail.messageSaveSuccess', 0, { name: currencyName });
            return this.currency.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
