import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-snippet-detail.html.twig';

Component.register('sw-settings-snippet-detail', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            snippet: {}
        };
    },

    computed: {
        snippetSetStore() {
            return State.getStore('snippet_set');
        },
        snippetStore() {
            return State.getStore('snippet');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                const snippetId = this.$route.params.id;
                this.snippet = this.snippetStore.getById(snippetId);
            }
            if (this.$route.params.setId) {
                this.snippet.setId = this.$route.params.setId;
            }
        },

        onSave() {
            this.snippet.languageId = '20080911ffff4fffafffffff19830531';

            const snippetKey = this.snippet.translationKey;
            const titleSaveSuccess = this.$tc('sw-settings-snippet.detail.titleSaveSuccess');
            const titleSaveError = this.$tc('sw-settings-snippet.detail.titleSaveError');
            const messageSaveSuccess = this.$tc('sw-settings-snippet.detail.messageSaveSuccess', 0, { key: snippetKey });
            const messageSaveError = this.$tc('sw-settings-snippet.detail.messageSaveError', 0, { key: snippetKey });
            return this.snippet.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            }).catch(() => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
            });
        }
    }
});
