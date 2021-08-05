import Sanitizer from 'src/core/helper/sanitizer.helper';
import template from './sw-settings-snippet-detail.html.twig';

const { Component, Mixin, Data: { Criteria } } = Shopware;
const ShopwareError = Shopware.Classes.ShopwareError;
const utils = Shopware.Utils;

Component.register('sw-settings-snippet-detail', {
    template,

    inject: [
        'snippetSetService',
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: true,
            isCreate: false,
            isAddedSnippet: false,
            isSaveable: true,
            isInvalidKey: false,
            queryIds: this.$route.query.ids,
            page: this.$route.query.page,
            limit: this.$route.query.limit,
            moduleData: this.$route.meta.$module,
            translationKey: '',
            translationKeyOrigin: '',
            snippets: [],
            sets: {},
            isSaveSuccessful: false,
            pushParams: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        identifier() {
            return this.translationKey;
        },

        snippetRepository() {
            return this.repositoryFactory.create('snippet');
        },

        snippetSetRepository() {
            return this.repositoryFactory.create('snippet_set');
        },

        snippetSetCriteria() {
            const criteria = new Criteria();

            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        backPath() {
            if (this.$route.query.ids && this.$route.query.ids.length > 0) {
                return {
                    name: 'sw.settings.snippet.list',
                    query: {
                        ids: this.$route.query.ids,
                        limit: this.$route.query.limit,
                        page: this.$route.query.page,
                    },
                };
            }
            return { name: 'sw.settings.snippet.index' };
        },

        invalidKeyError() {
            if (this.isInvalidKey) {
                return new ShopwareError({ code: 'DUPLICATED_SNIPPET_KEY', parameters: { key: this.translationKey } });
            }
            return null;
        },

        currentAuthor: {
            get() {
                return this._currentAuthor ||
                    `user/${Shopware.State.get('session').currentUser.username}`;
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.translationKeyOrigin = this.$route.params.key;
            this.prepareContent();
        },

        prepareContent() {
            this.isLoading = true;

            if (!this.$route.params.key && !this.isCreate) {
                this.onNewKeyRedirect();
            }
            this.translationKey = this.$route.params.key || '';

            this.snippetSetRepository.search(this.snippetSetCriteria).then((sets) => {
                this.sets = sets;
                this.initializeSnippet();
            }).finally(() => {
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

        applySnippetsToDummies(snippets) {
            const dummySnippets = this.snippets;

            dummySnippets.forEach(dummySnippet => {
                const realSnippet = snippets.find(snippet => dummySnippet.setId === snippet.setId);

                if (realSnippet) {
                    dummySnippet.author = realSnippet.author;
                    dummySnippet.id = realSnippet.id;
                    dummySnippet.value = realSnippet.value;
                    dummySnippet.origin = realSnippet.origin;
                    dummySnippet.translationKey = realSnippet.translationKey;
                    dummySnippet.setId = realSnippet.setId;

                    if (realSnippet.id) {
                        dummySnippet._isNew = false;
                    }
                }

                return dummySnippet;
            });

            this.isAddedSnippet = snippets.some(snippet => snippet.author.startsWith('user/') || snippet.author === '');
        },

        createSnippetDummy() {
            const snippets = [];
            this.sets.forEach((set) => {
                const snippetDummy = this.snippetRepository.create();

                snippetDummy.author = this.currentAuthor;
                snippetDummy.id = null;
                snippetDummy.value = null;
                snippetDummy.origin = null;
                snippetDummy.translationKey = this.translationKey;
                snippetDummy.setId = set.id;

                snippets.push(snippetDummy);
            });

            return snippets;
        },

        saveFinish() {
            this.isSaveSuccessful = false;

            this.$router.push({
                name: 'sw.settings.snippet.detail',
                params: this.pushParams,
                query: {
                    ids: this.queryIds,
                    page: this.page,
                    limit: this.limit,
                },
            });
        },

        onSave() {
            const responses = [];
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.isSaveable = this.checkIsSaveable();

            if (!this.isSaveable) {
                this.isLoading = false;
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc(
                        'sw-settings-snippet.detail.messageSaveError',
                        0,
                        { key: this.translationKey },
                    ),
                });
            }

            this.snippets.forEach((snippet) => {
                if (!snippet.author) {
                    snippet.author = this.currentAuthor;
                }
                snippet.value = Sanitizer.sanitize(snippet.value);

                if (!snippet.hasOwnProperty('value') || snippet.value === null) {
                    // If you clear the input-box, reset it to its origin value
                    snippet.value = snippet.origin;
                }

                if (snippet.translationKey !== this.translationKey) {
                    // On TranslationKey change, delete old snippets, but insert a copy with the new translationKey
                    if (snippet.id !== null) {
                        responses.push(
                            this.snippetRepository.delete(snippet.id),
                        );
                    }

                    if (snippet.value === null || snippet.value === '') {
                        return;
                    }

                    snippet.translationKey = this.translationKey;
                    snippet.id = null;

                    responses.push(
                        this.snippetRepository.save(snippet),
                    );
                } else if (snippet.origin !== snippet.value) {
                    // Only save if values differs from origin
                    responses.push(
                        this.snippetRepository.save(snippet),
                    );
                } else if (snippet.hasOwnProperty('id') && snippet.id !== null) {
                    // There's no need to keep a snippet which is exactly like the file-snippet, so delete
                    responses.push(
                        this.snippetRepository.delete(snippet.id),
                    );
                }
            });

            Promise.all(responses).then(() => {
                this.onNewKeyRedirect(true);
                this.prepareContent();
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((error) => {
                let errormsg = '';
                this.isLoading = false;
                if (error.response.data.errors.length > 0) {
                    errormsg = '<br/>Error Message: "'.concat(error.response.data.errors[0].detail).concat('"');
                }
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc(
                        'sw-settings-snippet.detail.messageSaveError',
                        0,
                        { key: this.translationKey },
                    ) + errormsg,
                });
            });
        },

        onChange() {
            if (!this.translationKey || this.translationKey.trim().length <= 0) {
                this.isSaveable = false;
                this.isInvalidKey = true;
                return;
            }
            this.isInvalidKey = false;

            this.doChange();
        },

        doChange: utils.debounce(function executeChange() {
            this.getCustomList().then((response) => {
                this.isSaveable = false;
                if (!response.total || Object.keys(response.data)[0] === this.translationKeyOrigin) {
                    this.isSaveable = this.checkIsSaveable();
                    return;
                }

                this.isInvalidKey = true;
                this.isSaveable = false;
            });

            if (!this.isSaveable) {
                return;
            }

            if ((this.isCreate || this.isAddedSnippet)) {
                this.translationKey = this.translationKey.trim();
            }
        }, 1000),

        onNewKeyRedirect(isNewOrigin = false) {
            this.isSaveSuccessful = true;
            const params = {
                key: this.translationKey,
            };

            if (isNewOrigin) {
                params.origin = this.translationKey;
            }

            this.isCreate = false;
            this.pushParams = params;
        },

        getCustomList() {
            return this.snippetSetService.getCustomList(1, 25, { translationKey: [this.translationKey] });
        },

        checkIsSaveable() {
            let count = 0;
            this.snippets.forEach((snippet) => {
                if (snippet.value === null) {
                    return;
                }

                if (this.translationKey.trim() !== this.translationKeyOrigin) {
                    count += 1;
                }

                if (snippet.value.trim().length >= 0) {
                    count += 1;
                }
            });

            return count > 0;
        },

        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role),
                message: this.$tc('sw-privileges.tooltip.warning'),
            };
        },
    },
});
