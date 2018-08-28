import { Component, State } from 'src/core/shopware';
import template from './sw-settings-language-list.html.twig';

Component.register('sw-settings-language-list', {
    template,

    mixins: [
        'listing',
        'notification'
    ],

    data() {
        return {
            languages: [],
            isLoading: false,
            showDeleteModal: false
        };
    },

    computed: {
        languageStore() {
            return State.getStore('language');
        }
    },

    created() {
        this.$root.$on('search', this.onSearch);
    },

    destroyed() {
        this.$root.$off('search', this.onSearch);
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.languages = [];

            return this.languageStore.getList(params).then((response) => {
                this.total = response.total;
                this.languages = response.items;
                this.isLoading = false;

                return this.languages;
            });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            const language = this.languageStore.store[id];
            const languageName = language.name;
            const titleSaveSuccess = this.$tc('sw-settings-language.list.titleDeleteSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-language.list.messageDeleteSuccess', 0, { name: languageName });

            this.languageStore.store[id].delete(true).then(() => {
                this.showDeleteModal = false;

                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });

                this.getList();
            }).catch(this.onCloseDeleteModal());
        },

        onInlineEditSave(item) {
            this.isLoading = true;

            item.save().then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        }
    }
});
