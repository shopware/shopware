import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-country-detail.html.twig';
import './sw-settings-country-detail.scss';

Component.register('sw-settings-country-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('country')
    ],

    data() {
        return {
            country: {}
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.country, 'name');
        },

        countryStore() {
            return State.getStore('country');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.countryId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.country = this.countryStore.getById(this.countryId);
        },

        onSave() {
            const countryName = this.country.name || this.country.translated.name;
            const titleSaveSuccess = this.$tc('sw-settings-country.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-country.detail.messageSaveSuccess', 0, {
                name: countryName
            });

            return this.country.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        },

        abortOnLanguageChange() {
            return Object.keys(this.country.getChanges()).length > 0;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        }
    }
});
