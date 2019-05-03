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
            currency: {},
            isLoading: false,
            isSaveSuccessful: false
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

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.currency.save().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch(() => {
                this.isLoading = false;
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
