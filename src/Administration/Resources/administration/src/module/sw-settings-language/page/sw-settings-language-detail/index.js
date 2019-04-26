import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-language-detail.html.twig';
import './sw-settings-language-detail.scss';

Component.register('sw-settings-language-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('language')
    ],

    data() {
        return {
            language: {},
            locales: [],
            languages: [],
            usedLocales: [],
            showAlertForChangeParentLanguage: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.language.name || '';
        },

        languageStore() {
            return State.getStore('language');
        },

        localeStore() {
            return State.getStore('locale');
        },

        isIsoCodeRequired() {
            return !this.language.parentId || this.language.parentId.length < 1;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.languageId = this.$route.params.id;
                this.loadEntityData();
            }

            this.languageStore.getList({
                page: 1,
                limit: 1,
                aggregations: {
                    usedLocales: { name: 'usedLocales', type: 'value_count', field: 'language.locale.code' }
                }
            }).then((response) => {
                this.usedLocales = response.aggregations.usedLocales[0].values;
            });
        },

        loadEntityData() {
            this.language = this.languageStore.getById(this.languageId);
        },

        onInputLanguage() {
            if (this.language.isLocal || !this.language.original.parentId) {
                return;
            }

            this.showAlertForChangeParentLanguage = this.language.getChanges().hasOwnProperty('parentId');
        },

        showOption(item) {
            return item.id !== this.language.id;
        },

        isLocaleAlreadyUsed(item) {
            if (item.code === this.language.locale.code) {
                return false;
            }

            const foundLocale = this.usedLocales.find((locale) => {
                return item.code === locale.key;
            });

            return foundLocale !== undefined;
        },

        onSave() {
            const languageName = this.language.name;
            const titleSaveSuccess = this.$tc('sw-settings-language.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-language.detail.messageSaveSuccess', 0, { name: languageName });
            return this.language.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        },

        onChangeLanguage() {
            this.loadEntityData();
        }
    }
});
