import { Component, State, Mixin } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-settings-snippet-detail.html.twig';

Component.register('sw-settings-snippet-detail', {
    template,

    inject: ['snippetService', 'snippetSetService', 'userService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: true,
            isCreate: false,
            isAddedSnippet: false,
            isSavable: true,
            isInvalidKey: false,
            queryIds: this.$route.query.ids,
            page: this.$route.query.page,
            limit: this.$route.query.limit,
            moduleData: this.$route.meta.$module,
            translationKey: '',
            translationKeyOrigin: '',
            snippets: [],
            sets: {}
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.translationKey;
        },

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
                    (this.translationKey !== null && this.translationKey.trim().length > 0) + 1,
                    {
                        key: this.translationKey
                    }
                );
            }

            return '';
        }
    },

    created() {
        this.translationKeyOrigin = this.$route.params.origin;
        this.prepareContent();
    },

    methods: {
        prepareContent() {
            this.isLoading = true;
            this.userService.getUser().then((response) => {
                this.currentAuthor = `user/${response.data.username}`;
            });

            if (!this.$route.params.key && !this.isCreate) {
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
            this.getCustomList().then((response) => {
                if (!response.total) {
                    this.isAddedSnippet = true;
                    return;
                }
                this.applySnippetsToDummies(response.data[this.translationKey]);
            });
        },

        applySnippetsToDummies(data) {
            const snippets = this.snippets;
            const patchedSnippets = [];
            snippets.forEach((snippet) => {
                const newSnippet = data.find(item => item.setId === snippet.setId);
                if (newSnippet) {
                    snippet = newSnippet;
                }
                patchedSnippets.push(snippet);
            });
            this.snippets = patchedSnippets;
            this.isAddedSnippet = data.some(item => item.author.startsWith('user/') || item.author === '');
        },

        createSnippetDummy() {
            const snippets = [];
            Object.values(this.sets).forEach((set) => {
                snippets.push({
                    author: this.currentAuthor,
                    id: null,
                    value: null,
                    origin: null,
                    translationKey: this.translationKey,
                    setId: set.id
                });
            });

            return snippets;
        },

        onSave() {
            const responses = [];
            this.snippets.forEach((snippet) => {
                if (!snippet.author) {
                    snippet.author = this.currentAuthor;
                }

                if (!snippet.hasOwnProperty('value') || snippet.value === null) {
                    // If you clear the input-box, reset it to its origin value
                    snippet.value = snippet.origin;
                }

                if (snippet.translationKey !== this.translationKey) {
                    // On TranslationKey change, delete old snippets, but insert a copy with the new translationKey
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
                    // Only save if values differs from origin
                    responses.push(this.snippetService.save(snippet));
                } else if (snippet.hasOwnProperty('id') && snippet.id !== null) {
                    // There's no need to keep a snippet which is exactly like the file-snippet, so delete
                    responses.push(this.snippetService.delete(snippet.id));
                }
            });

            Promise.all(responses).then(() => {
                this.onNewKeyRedirect(true);
                this.prepareContent();
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-snippet.detail.titleSaveSuccess'),
                    message: this.$tc(
                        'sw-settings-snippet.detail.messageSaveSuccess',
                        0,
                        { key: this.translationKey }
                    )
                });
            }).catch(() => {
                this.prepareContent();
                this.createNotificationError({
                    title: this.$tc('sw-settings-snippet.detail.titleSaveError'),
                    message: this.$tc(
                        'sw-settings-snippet.detail.messageSaveError',
                        0,
                        { key: this.translationKey }
                    )
                });
            });
        },

        onChange() {
            this.isSavable = false;

            if (!this.translationKey || this.translationKey.trim().length <= 0) {
                this.isInvalidKey = true;
                return;
            }
            this.isInvalidKey = false;

            this.doChange();
        },

        doChange: utils.debounce(function executeChange() {
            this.getCustomList().then((response) => {
                if (!response.total || Object.keys(response.data)[0] === this.translationKeyOrigin) {
                    this.isSavable = this.isSaveable();
                    return;
                }

                this.isInvalidKey = true;
                this.isSavable = false;
            });

            if (!this.isSavable) {
                return;
            }

            this.translationKey = this.translationKey.trim().toLowerCase();
        }, 500),

        onNewKeyRedirect(isNewOrigin = false) {
            const params = {
                key: this.translationKey
            };

            if (isNewOrigin) {
                params.origin = this.translationKey;
            }

            this.isCreate = false;
            this.$router.push({
                name: 'sw.settings.snippet.detail',
                params,
                query: {
                    ids: this.queryIds,
                    page: this.page,
                    limit: this.limit
                }
            });
        },

        getCustomList() {
            return this.snippetSetService.getCustomList(1, 25, { translationKey: [this.translationKey] });
        },

        isSaveable() {
            let count = 0;
            this.snippets.forEach((snippet) => {
                if (snippet.value === null || snippet.value.trim() === '') {
                    return;
                }

                if (this.translationKey.trim() !== this.translationKeyOrigin) {
                    count += 1;
                }

                if (snippet.origin === snippet.value) {
                    return;
                }

                if (snippet.value.trim().length > 0) {
                    count += 1;
                }
            });

            return count > 0;
        }
    }
});
