import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-language-detail.html.twig';

Component.register('sw-settings-language-detail', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            language: {},
            locales: [],
            languages: []
        };
    },

    computed: {
        languageStore() {
            return State.getStore('language');
        },

        localeStore() {
            return State.getStore('locale');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.languageId = this.$route.params.id;
                this.language = this.languageStore.getById(this.languageId);
            }

            this.localeStore.getList({
                offset: 0,
                limit: 300
            }).then((response) => {
                this.locales = response.items;
            });

            this.languageStore.getList({
                offset: 0,
                limit: 20
            }).then((response) => {
                this.languages = response.items;
            });
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
        }
    }
});
