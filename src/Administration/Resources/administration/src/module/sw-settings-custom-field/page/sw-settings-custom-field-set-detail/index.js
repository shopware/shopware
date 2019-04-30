import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-custom-field-set-detail.html.twig';

Component.register('sw-settings-custom-field-set-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet'),
        Mixin.getByName('discard-detail-page-changes')('set')
    ],

    data() {
        return {
            set: {},
            setId: ''
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.set.config && this.set.config.label
                ? this.getInlineSnippet(this.set.config.label)
                : this.set.name;
        },

        customFieldSetStore() {
            return State.getStore('custom_field_set');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.setId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.set = this.customFieldSetStore.getById(this.setId);
        },

        onSave() {
            const setLabel = this.identifier;
            const titleSaveSuccess = this.$tc('sw-settings-custom-field.set.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-custom-field.set.detail.messageSaveSuccess', 0, {
                name: setLabel
            });

            // Remove all translations except for default locale(fallbackLanguage)
            // in case, the set is not translated
            if (!this.set.config.translated || this.set.config.translated === false) {
                const fallbackLocale = this.swInlineSnippetFallbackLocale;
                this.set.config.label = { [fallbackLocale]: this.set.config.label[fallbackLocale] };
            }

            if (!this.set.relations) {
                this.set.relations = [];
            }

            return this.set.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });

                this.$refs.customFieldList.getList();
            });
        },

        abortOnLanguageChange() {
            return Object.keys(this.set.getChanges()).length > 0;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        }
    }
});
