import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-currency-detail.html.twig';

Component.register('sw-settings-currency-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('currency')
    ],

    data() {
        return {
            currency: {}
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.currency, 'name');
        },

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
                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.currency = this.currencyStore.getById(this.currencyId);
        },

        onSave() {
            const currencyName = this.currency.name || this.currency.translated.name;
            const titleSaveSuccess = this.$tc('sw-settings-currency.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-currency.detail.messageSaveSuccess', 0, { name: currencyName });
            return this.currency.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        },

        abortOnLanguageChange() {
            return Object.keys(this.currency.getChanges()).length > 0;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        }
    }
});
