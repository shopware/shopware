import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-snippet-detail.html.twig';

Component.register('sw-settings-snippet-detail', {
    template,

    inject: ['snippetService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('snippet')
    ],

    data() {
        return {
            isLoading: true,
            isCreate: false,
            page: this.$route.query.page,
            limit: this.$route.query.limit,
            moduleData: this.$route.meta.$module,
            translationKey: '',
            snippets: [],
            sets: {}
        };
    },

    computed: {
        snippetSetStore() {
            return State.getStore('snippet_set');
        },
        backPath() {
            if (this.$route.query.ids && this.$route.query.ids.length > 0) {
                return {
                    name: 'sw.settings.snippet.list',
                    query: {
                        ids: this.$route.query.ids,
                        limit: this.$route.query.limit,
                        page: this.$route.query.page
                    }
                };
            }
            return { name: 'sw.settings.snippet.index' };
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.key) {
                this.$router.back();

                return;
            }

            this.translationKey = this.$route.params.key;
            this.snippetSetStore.getList({}).then((response) => {
                this.sets = response.items;
            }).then(() => {
                this.initializeSnippet();
                this.isLoading = false;
            });
        },

        initializeSnippet() {
            this.snippetService.getByKey(this.translationKey, this.page, this.limit).then((response) => {
                if (!response) {
                    this.$router.back();

                    return;
                }
                this.snippets = response;
            });
        },

        onSave() {
            const responses = [];
            this.snippets.forEach((snippet) => {
                if (snippet.value === null) {
                    snippet.value = snippet.origin;
                }

                if (snippet.origin !== snippet.value) {
                    responses.push(this.snippetService.save(snippet));
                } else if (snippet.id !== null) {
                    responses.push(this.snippetService.delete(snippet.id));
                }
            });

            Promise.all(responses).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-snippet.detail.titleSaveSuccess'),
                    message: this.$tc(
                        'sw-settings-snippet.detail.messageSaveSuccess',
                        0,
                        { key: this.translationKey }
                    )
                });

                this.createdComponent();
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-snippet.detail.titleSaveError'),
                    message: this.$tc(
                        'sw-settings-snippet.detail.messageSaveError',
                        0,
                        { key: this.translationKey }
                    )
                });

                this.createdComponent();
            });
        }
    }
});
