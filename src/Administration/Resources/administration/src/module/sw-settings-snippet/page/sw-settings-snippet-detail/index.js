import { Component, State, Mixin } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-settings-snippet-detail.html.twig';

Component.register('sw-settings-snippet-detail', {
    template,

    inject: ['snippetService', 'snippetSetService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('snippet')
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
                        page: this.$route.query.page
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
            this.snippetSetStore.getList({ sortBy: 'name', sortDirection: 'ASC' }).then((response) => {
                const sets = [];

                response.items.forEach((set) => {
                    sets[set.id] = set;
                });
                this.sets = sets;
            }).then(() => {
                this.initializeSnippet();
                this.isLoading = false;
            });
        },

        initializeSnippet() {
            this.snippets = this.createSnippetDummy();
            this.snippetSetService.getCustomList(
                1,
                25,
                {
                    isCustom: false,
                    emptySnippets: false,
                    term: null,
                    namespaces: [],
                    authors: [],
                    translationKeys: []
                }
            ).then((response) => {
                if (!response.total) {
                    this.isCustomState = true;
                    return;
                }
                this.snippets = response.data[this.translationKey];
                this.isCustomState = response.data[this.translationKey].origin === '';
            });
        },

        createSnippetDummy() {
            const snippets = [];
            this.sets.forEach((set) => {
                snippets.push({
                    author: '1',
                    id: null,
                    value: null,
                    translationKey: this.translationKey,
                    setId: set.id
                });
            });

            return snippets;
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
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-snippet.detail.titleSaveError'),
                    message: this.$tc(
                        'sw-settings-snippet.detail.messageSaveError',
                        0,
                        { key: this.translationKey }
                    )
                });
            }).finally(() => {
                this.createdComponent();
            });
        },

        debouncedTranslationKeyChange: utils.debounce(function debouncedSearch() {
            if (
                !this.translationKey ||
                this.translationKey === this.$route.params.key ||
                this.translationKey.trim().length <= 0
            ) {
                this.isInvalidKey = true;
                return;
            }

            this.translationKey = this.translationKey.trim();
            this.isInvalidKey = false;
            this.isLoading = true;
            this.isSavable = true;

            this.snippetSetService.getCustomList(
                1,
                25,
                {
                    isCustom: false,
                    emptySnippets: false,
                    term: null,
                    namespaces: [],
                    authors: [],
                    translationKeys: [this.translationKey]
                }
            ).then((response) => {
                if (!response.total) {
                    this.onNewKeyRedirect();
                    return;
                }

                this.isInvalidKey = true;
            }).finally(() => {
                this.isLoading = false;
            });
        }, 500),

        onNewKeyRedirect() {
            this.$router.push({
                name: 'sw.settings.snippet.detail',
                params: {
                    key: this.translationKey
                },
                query: {
                    ids: this.queryIds,
                    page: this.page,
                    limit: this.limit
                }
            });
        }
    }
});
