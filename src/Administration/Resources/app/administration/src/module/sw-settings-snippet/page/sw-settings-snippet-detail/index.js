/**
 * @sw-package discovery
 */
import template from './sw-settings-snippet-detail.html.twig';
import './sw-settings-snippet-detail.scss';

const {
    Mixin,
    Data: { Criteria },
} = Shopware;
const ShopwareError = Shopware.Classes.ShopwareError;
const utils = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
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
            isLoadingSnippets: true,
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
            const criteria = new Criteria(1, null);

            criteria.addSorting(Criteria.sort('name', 'ASC'));

            return criteria;
        },

        backPath() {
            if (this.$route.query?.ids?.length > 0) {
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
                return new ShopwareError({
                    code: 'DUPLICATED_SNIPPET_KEY',
                    parameters: { key: this.translationKey },
                });
            }
            return null;
        },

        currentAuthor: {
            get() {
                return this._currentAuthor || `user/${Shopware.Store.get('session').currentUser.username}`;
            },
        },

        snippetStates() {
            return Object.fromEntries(
                this.snippets.map((s) => [
                    s.setId,
                    this.getSnippetState(s),
                ]),
            );
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
            this.isSaveable = true;

            if (!this.$route.params.key && !this.isCreate) {
                this.onNewKeyRedirect();
            }
            this.translationKey = this.$route.params.key || '';

            this.snippetSetRepository
                .search(this.snippetSetCriteria)
                .then((sets) => {
                    this.sets = sets;
                    this.isLoadingSnippets = true;
                    this.initializeSnippet();
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        initializeSnippet() {
            this.snippets = this.createSnippetDummy();
            this.getCustomList()
                .then((response) => {
                    if (!response.total) {
                        this.isAddedSnippet = true;
                        return;
                    }

                    this.applySnippetsToDummies(response.data[this.translationKey]);
                })
                .finally(() => {
                    this.isLoadingSnippets = false;
                });
        },

        applySnippetsToDummies(snippets) {
            const dummySnippets = this.snippets;

            dummySnippets.forEach((dummySnippet) => {
                const realSnippet = snippets.find((snippet) => dummySnippet.setId === snippet.setId);

                if (realSnippet) {
                    dummySnippet.author = realSnippet.author;
                    dummySnippet.id = realSnippet.id;
                    dummySnippet.value = realSnippet.value;
                    dummySnippet.origin = realSnippet.origin;
                    dummySnippet.resetTo = realSnippet.resetTo;
                    dummySnippet._overriding = false;
                    dummySnippet._savedValue = null;
                    dummySnippet._pendingDelete = false;
                    dummySnippet._hasFileValue = realSnippet.hasFileValue;
                    dummySnippet.translationKey = realSnippet.translationKey;
                    dummySnippet.setId = realSnippet.setId;

                    if (realSnippet.id) {
                        dummySnippet._isNew = false;
                    }
                }

                return dummySnippet;
            });

            this.isAddedSnippet = snippets.some((snippet) => snippet.author.startsWith('user/') || snippet.author === '');
        },

        createSnippetDummy() {
            const snippets = [];
            this.sets.forEach((set) => {
                const snippetDummy = this.snippetRepository.create();

                snippetDummy.author = this.currentAuthor;
                snippetDummy.id = null;
                snippetDummy.value = null;
                snippetDummy.origin = null;
                snippetDummy.resetTo = null;
                snippetDummy._overriding = false;
                snippetDummy._savedValue = null;
                snippetDummy._pendingDelete = false;
                snippetDummy._hasFileValue = false;
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

            if (!isNaN(this.translationKey)) {
                this.isLoading = false;
                this.createNotificationError({
                    message: this.$t('sw-settings-snippet.detail.messageSaveErrorNumericKey'),
                });

                return;
            }

            if (!this.isSaveable) {
                this.isLoading = false;
                this.createNotificationError({
                    message: this.$t('sw-settings-snippet.detail.messageSaveError', { key: this.translationKey }),
                });

                return;
            }

            this.snippets.forEach((snippet) => {
                if (!snippet.author) {
                    snippet.author = this.currentAuthor;
                }

                if (snippet._pendingDelete) {
                    responses.push(this.snippetRepository.delete(snippet.id));
                    return;
                }

                if (!snippet.hasOwnProperty('value') || snippet.value === null) {
                    if (snippet.origin === null) {
                        return;
                    }
                    // If you clear the input-box, reset it to its origin value
                    snippet.value = snippet.origin;
                }

                if (snippet.translationKey !== this.translationKey) {
                    snippet.translationKey = this.translationKey;
                    this.$route.params.key = this.translationKey;
                    this.translationKeyOrigin = this.translationKey;
                    responses.push(this.snippetRepository.save(snippet));
                } else if (snippet.origin !== snippet.value) {
                    // Only save if values differs from origin
                    responses.push(this.snippetRepository.save(snippet));
                } else if (snippet.hasOwnProperty('id') && snippet.id !== null) {
                    // There's no need to keep a snippet which is exactly like the file-snippet, so delete
                    responses.push(this.snippetRepository.delete(snippet.id));
                }
            });

            this.snippets = [];

            Promise.all(responses)
                .then(() => {
                    this.onNewKeyRedirect(true);
                    this.isSaveSuccessful = true;
                })
                .catch((error) => {
                    const errorSnippet = this.$t('sw-settings-snippet.detail.messageSaveError', {
                        key: this.translationKey,
                    });

                    let errorMessage = '';
                    if (error.response.data.errors.length > 0) {
                        errorMessage = `<br/>Error Message: "${error.response.data.errors[0].detail}"`;
                    }

                    this.createNotificationError({
                        message: errorSnippet + errorMessage,
                    });
                })
                .finally(() => {
                    this.prepareContent();
                    this.isLoading = false;
                });
        },

        onChange(snippet, value) {
            if (snippet) {
                snippet.value = value;
            }

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

            if (this.isCreate || this.isAddedSnippet) {
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
            return this.snippetSetService.getCustomList(1, 25, {
                translationKey: [this.translationKey],
            });
        },

        checkIsSaveable() {
            return this.snippets.some((snippet) => {
                if (snippet._pendingDelete) {
                    return true;
                }

                if (snippet.value === null) {
                    return false;
                }

                return this.translationKey.trim() !== this.translationKeyOrigin || snippet.value.trim().length >= 0;
            });
        },

        getPlaceholder(snippet) {
            const emptyPlaceholder = this.$t('sw-settings-snippet.general.placeholderValue');
            if (this.snippetStates[snippet.setId] === 'empty') {
                return emptyPlaceholder;
            }

            return snippet.resetTo || snippet.origin || emptyPlaceholder;
        },

        getSnippetState(snippet) {
            if (snippet.id !== null) {
                if (snippet._pendingDelete) {
                    return 'inherited';
                }

                if (!snippet._hasFileValue) {
                    return snippet.value ? 'custom' : 'empty';
                }

                return snippet.value !== null ? 'overridden' : 'inherited';
            }

            const hasFileValue = snippet._hasFileValue ?? !!snippet.origin;

            if (!hasFileValue && !snippet.value) {
                return 'empty';
            }

            if (snippet._overriding) {
                return 'overriding';
            }

            if (!hasFileValue) {
                return 'custom';
            }

            return 'inherited';
        },

        onRemoveInheritance(snippet) {
            if (snippet._pendingDelete) {
                // Undo a pending restore: put the saved DB value back
                snippet.value = snippet._savedValue;
                snippet._savedValue = null;
                snippet._pendingDelete = false;
            } else {
                // Start overriding a file-only snippet
                snippet._overriding = true;
            }
            this.isSaveable = this.checkIsSaveable();
        },

        onResetSnippet(snippet) {
            if (snippet._overriding) {
                // Was a file snippet in edit mode: restore to file value
                snippet.value = snippet.origin;
                snippet._overriding = false;
            } else {
                // DB-overridden snippet: save the current value for undo, then clear
                snippet._savedValue = snippet.value;
                snippet.value = null;
                snippet._pendingDelete = true;
            }
            this.isSaveable = this.checkIsSaveable();
        },

        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role),
                message: this.$t('sw-privileges.tooltip.warning'),
            };
        },
    },
};
