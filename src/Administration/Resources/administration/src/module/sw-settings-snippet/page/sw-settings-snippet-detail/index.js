import { Component, State, Mixin } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-settings-snippet-detail.html.twig';

Component.register('sw-settings-snippet-detail', {
    template,

    inject: ['snippetService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: true,
            isCreate: false,
            isCustomState: this.$route.query.isCustomState,
            isSavable: true,
            isInvalidKey: false,
            queryIds: this.$route.query.ids,
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
                        page: this.$route.query.page,
                        isCustomState: this.isCustomState
                    }
                };
            }
            return { name: 'sw.settings.snippet.index' };
        },
        invalidKeyErrorMessage() {
            if (this.isInvalidKey) {
                return this.$tc(
                    'sw-settings-snippet.detail.messageKeyExists',
                    (this.translationKey !== null) + 1,
                    {
                        key: this.translationKey
                    }
                );
            }
            return '';
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.key && !this.isCreate) {
                this.isCreate = true;
                this.onNewKeyRedirect();
            }

            this.translationKey = this.$route.params.key || '';
            this.snippetSetStore.getList({}).then((response) => {
                this.sets = response.items;
            }).then(() => {
                this.initializeSnippet();
                this.isLoading = false;
            });
        },

        initializeSnippet() {
            const isCustom = this.isCustomState || this.isCreate;
            this.snippetService.getByKey(this.translationKey, this.page, this.limit, isCustom).then((response) => {
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

                if (snippet.translationKey !== this.translationKey) {
                    if (snippet.id !== null) {
                        responses.push(this.snippetService.delete(snippet.id));
                    }
                    if (snippet.value === null || snippet.value === '') {
                        return;
                    }

                    snippet.translationKey = this.translationKey;
                    snippet.id = null;
                    responses.push(this.snippetService.save(snippet));
                } else if (snippet.origin !== snippet.value) {
                    responses.push(this.snippetService.save(snippet));
                } else if (snippet.id !== null) {
                    responses.push(this.snippetService.delete(snippet.id));
                }
            });

            Promise.all(responses).then(() => {
                this.onNewKeyRedirect();
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
        },

        debouncedTranslationKeyChange: utils.debounce(function debouncedSearch() {
            if (
                !this.translationKey ||
                this.translationKey === this.$route.params.key ||
                this.translationKey.length <= 0 ||
                this.translationKey.trim() <= 0
            ) {
                this.isInvalidKey = true;
                return;
            }

            this.translationKey = this.translationKey.trim();
            this.isInvalidKey = false;
            this.isLoading = true;
            this.isSavable = true;
            let keyAlreadyExists = false;
            const responses = [];

            responses.push(
                this.snippetService.getByKey(this.translationKey, this.page, this.limit, true).then((response) => {
                    response.forEach((item) => {
                        keyAlreadyExists = keyAlreadyExists || (item.id !== null);
                    });
                })
            );

            responses.push(
                this.snippetService.getByKey(this.translationKey, this.page, this.limit, false).then((response) => {
                    keyAlreadyExists = keyAlreadyExists || (response.data !== false);
                })
            );

            Promise.all(responses).then(() => {
                this.isLoading = false;
                this.isInvalidKey = keyAlreadyExists;

                if (!keyAlreadyExists) {
                    this.onNewKeyRedirect();
                }
            });
        }, 500),

        onNewKeyRedirect() {
            this.$router.push({
                name: 'sw.settings.snippet.detail',
                params: {
                    key: this.translationKey
                },
                query: {
                    isCustomState: this.isCustomState,
                    ids: this.queryIds,
                    page: this.page,
                    limit: this.limit
                }
            });
        }
    }
});
