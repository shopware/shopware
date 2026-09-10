import template from './sw-landing-page-tree.html.twig';
import './sw-landing-page-tree.scss';

const { Criteria } = Shopware.Data;

// shopware.api.max_limit caps every Admin API request, rejecting anything higher instead of clamping.
// It is configurable but defaults to 500, which the Administration hardcodes everywhere; stay consistent
// with that until the value is exposed to the client.
const PAGE_SIZE = 500;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'syncService',
        'acl',
    ],

    emits: [
        'landing-page-checked-elements-count',
        'unsaved-changes',
    ],

    mixins: [
        'notification',
    ],

    props: {
        landingPageId: {
            type: String,
            required: false,
            default: null,
        },

        currentLanguageId: {
            type: String,
            required: true,
        },

        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },

        allowCreate: {
            type: Boolean,
            required: false,
            default: true,
        },

        allowDelete: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            loadedLandingPages: {},
            translationContext: 'sw-landing-page',
            linkContext: 'sw.category.landingPageDetail',
            isLoadingInitialData: true,
            isLoadingMore: false,
            page: 1,
            total: 0,
        };
    },

    computed: {
        landingPagesToDelete() {
            return Shopware.Store.get('swCategoryDetail').landingPagesToDelete;
        },

        cmsLandingPageCriteria() {
            const criteria = new Criteria(this.page, PAGE_SIZE);
            criteria.addSorting(Criteria.sort('name'));
            // Names are not unique, so paging without a stable tiebreaker can skip or repeat entries.
            criteria.addSorting(Criteria.sort('id'));

            return criteria;
        },

        landingPage() {
            return Shopware.Store.get('swCategoryDetail').landingPage;
        },

        landingPageRepository() {
            return this.repositoryFactory.create('landing_page');
        },

        landingPages() {
            return Object.values(this.loadedLandingPages);
        },

        hasMoreLandingPages() {
            return this.landingPages.length < this.total;
        },

        disableContextMenu() {
            if (!this.allowEdit) {
                return true;
            }

            return this.currentLanguageId !== Shopware.Context.api.systemLanguageId;
        },

        contextMenuTooltipText() {
            if (!this.allowEdit) {
                return this.$t('sw-privileges.tooltip.warning');
            }

            return null;
        },
    },

    watch: {
        landingPagesToDelete(value) {
            if (value === undefined) {
                return;
            }

            this.$refs.landingPageTree.onDeleteElements(value);

            Shopware.Store.get('swCategoryDetail').landingPagesToDelete = undefined;
        },

        landingPage(newVal, oldVal) {
            // load data when path is available
            if (!oldVal && this.isLoadingInitialData) {
                this.loadLandingPages();
                return;
            }

            // back to index
            if (newVal === null) {
                return;
            }

            // reload after save
            if (oldVal && this.landingPageId !== 'create' && newVal.id === oldVal.id) {
                this.landingPageRepository.get(newVal.id).then((newLandingPage) => {
                    this.loadedLandingPages[newLandingPage.id] = newLandingPage;
                });
            }
        },

        currentLanguageId() {
            this.isLoadingInitialData = true;
            this.resetLandingPages();

            this.loadLandingPages().finally(() => {
                this.isLoadingInitialData = false;
            });
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadLandingPages()
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('global.notification.unspecifiedSaveErrorMessage'),
                    });
                })
                .finally(() => {
                    this.isLoadingInitialData = false;
                });
        },

        loadLandingPages() {
            return this.landingPageRepository.search(this.cmsLandingPageCriteria).then((result) => {
                this.total = result.total ?? result.length;
                this.addLandingPages(result);
            });
        },

        loadMoreLandingPages() {
            this.isLoadingMore = true;
            this.page += 1;

            return this.loadLandingPages()
                .catch(() => {
                    this.page -= 1;

                    this.createNotificationError({
                        message: this.$t('global.notification.unspecifiedSaveErrorMessage'),
                    });
                })
                .finally(() => {
                    this.isLoadingMore = false;
                });
        },

        resetLandingPages() {
            this.page = 1;
            this.total = 0;
            this.loadedLandingPages = {};
        },

        // Offsets shift as soon as entries are added, removed or renamed, so every page that was
        // already loaded has to be fetched again to stay in sync with the server ordering.
        async reloadLandingPages() {
            const loadedPages = this.page;
            const reloaded = {};
            let total = 0;

            for (let page = 1; page <= loadedPages; page += 1) {
                this.page = page;

                const result = await this.landingPageRepository.search(this.cmsLandingPageCriteria);

                total = result.total ?? result.length;
                result.forEach((landingPage) => {
                    reloaded[landingPage.id] = landingPage;
                });
            }

            // Swapped in one go: emptying the map first would flash an empty tree on every mutation.
            this.total = total;
            this.loadedLandingPages = reloaded;
        },

        checkedElementsCount(count) {
            this.$emit('landing-page-checked-elements-count', count);
        },

        deleteCheckedItems(checkedItems) {
            const ids = Object.keys(checkedItems);

            return this.landingPageRepository.syncDeleted(ids).then(() => {
                ids.forEach((id) => this.removeFromStore(id));

                return this.reloadLandingPages();
            });
        },

        onDeleteLandingPage({ data: landingPage }) {
            if (landingPage.isNew()) {
                delete this.loadedLandingPages[landingPage.id];
                return Promise.resolve();
            }

            return this.landingPageRepository.delete(landingPage.id).then(() => {
                this.removeFromStore(landingPage.id);

                if (landingPage.id === this.landingPageId) {
                    this.$router.push({ name: 'sw.category.index' });
                }

                return this.reloadLandingPages();
            });
        },

        changeLandingPage(landingPage) {
            const route = {
                name: 'sw.category.landingPageDetail',
                params: { id: landingPage.id },
            };

            if (this.landingPage && this.landingPageRepository.hasChanges(this.landingPage)) {
                this.$emit('unsaved-changes', route);
            } else {
                this.$router.push(route);
            }
        },

        duplicateElement(contextItem) {
            const behavior = {
                cloneChildren: false,
                overwrites: {
                    name: `${contextItem.data.name} ${this.$t('global.default.copy')}`,
                    url: `${contextItem.data.url}-${this.$t('global.default.copy')}`,
                    active: false,
                },
            };

            this.landingPageRepository
                .clone(contextItem.id, behavior, Shopware.Context.api)
                .then((clone) => {
                    return this.reloadLandingPages().then(() => {
                        const criteria = new Criteria(1, 25);
                        criteria.setIds([clone.id]);

                        return this.landingPageRepository.search(criteria).then((landingPages) => {
                            landingPages.forEach((element) => {
                                element.childCount = 0;
                                element.parentId = null;
                            });

                            this.addLandingPages(landingPages);
                        });
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$t('global.notification.unspecifiedSaveErrorMessage'),
                    });
                });
        },

        createNewElement(contextItem, parentId, name = '') {
            const newLandingPage = this.createNewLandingPage(name);
            this.addLandingPage(newLandingPage);
            return newLandingPage;
        },

        syncLandingPages() {
            return this.landingPageRepository.sync(this.landingPages).then(() => {
                return this.reloadLandingPages();
            });
        },

        createNewLandingPage(name) {
            const newLandingPage = this.landingPageRepository.create();

            newLandingPage.name = name;
            newLandingPage.active = false;

            newLandingPage.save = () => {
                return this.landingPageRepository.save(newLandingPage).then(() => {
                    return this.reloadLandingPages().then(() => {
                        const criteria = new Criteria(1, 25);
                        criteria.setIds([newLandingPage.id].filter((id) => id !== null));

                        return this.landingPageRepository.search(criteria).then((landingPages) => {
                            this.addLandingPages(landingPages);
                        });
                    });
                });
            };

            return newLandingPage;
        },

        addLandingPage(landingPage) {
            if (!landingPage) {
                return;
            }

            this.loadedLandingPages = {
                ...this.loadedLandingPages,
                [landingPage.id]: landingPage,
            };
        },

        addLandingPages(landingPages) {
            if (!landingPages) {
                return;
            }

            const existingLandingPageEntries = Object.entries(this.loadedLandingPages || {});
            const newLandingPageEntries = landingPages.map((landingPage) => {
                return [
                    landingPage.id,
                    landingPage,
                ];
            });

            this.loadedLandingPages = Object.fromEntries([
                ...existingLandingPageEntries,
                ...newLandingPageEntries,
            ]);
        },

        removeFromStore(id) {
            this.loadedLandingPages = Object.fromEntries(
                Object.entries(this.loadedLandingPages || {}).filter(([key]) => {
                    return key !== id;
                }),
            );
        },

        getLandingPageUrl(landingPage) {
            return this.$router.resolve({
                name: this.linkContext,
                params: { id: landingPage.id },
            }).href;
        },

        newLandingPageUrl() {
            return {
                name: 'sw.category.landingPageDetail',
                params: { id: 'create' },
            };
        },
    },
};
