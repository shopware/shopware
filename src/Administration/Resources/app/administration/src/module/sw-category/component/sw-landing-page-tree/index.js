import template from './sw-landing-page-tree.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

// Todo: Will be tested in NEXT-13222
Component.register('sw-landing-page-tree', {
    template,

    inject: ['repositoryFactory', 'syncService'],
    mixins: [
        'notification'
    ],

    props: {
        landingPageId: {
            type: String,
            required: false,
            default: null
        },

        currentLanguageId: {
            type: String,
            required: true
        },

        allowEdit: {
            type: Boolean,
            required: false,
            default: true
        },

        allowCreate: {
            type: Boolean,
            required: false,
            default: true
        },

        allowDelete: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    data() {
        return {
            loadedLandingPages: {},
            translationContext: 'sw-landing-page',
            linkContext: 'sw.category.landingPage',
            isLoadingInitialData: true
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        cmsLandingPageCriteria() {
            const criteria = new Criteria();
            criteria.limit = 500;
            criteria.addSorting(Criteria.sort('name'));

            return criteria;
        },

        landingPage() {
            return Shopware.State.get('swCategoryDetail').landingPage;
        },

        landingPageRepository() {
            return this.repositoryFactory.create('landing_page');
        },

        landingPages() {
            return Object.values(this.loadedLandingPages);
        },

        disableContextMenu() {
            if (!this.allowEdit) {
                return true;
            }

            return this.currentLanguageId !== Shopware.Context.api.systemLanguageId;
        },

        contextMenuTooltipText() {
            if (!this.allowEdit) {
                return this.$tc('sw-privileges.tooltip.warning');
            }

            return null;
        }
    },

    watch: {
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
            if (oldVal && newVal.id === oldVal.id) {
                this.landingPageRepository.get(newVal.id, Shopware.Context.api).then((newLandingPage) => {
                    this.$set(this.loadedLandingPages, newLandingPage.id, newLandingPage);
                });
            }
        },

        currentLanguageId() {
            this.openInitialTree();
        }
    },

    methods: {
        createdComponent() {
            this.loadLandingPages()
                .catch(() => {
                    this.createNotificationError({
                        title: this.$tc('global.default.error'),
                        message: this.$tc('global.notification.unspecifiedSaveErrorMessage')
                    });
                })
                .finally(() => {
                    this.isLoadingInitialData = false;
                });
        },

        loadLandingPages() {
            return this.landingPageRepository.search(this.cmsLandingPageCriteria, Shopware.Context.api).then((result) => {
                // Is needed for the sw-tree component
                result.forEach(element => {
                    element.childCount = 0;
                });
                this.addLandingPages(result);
            });
        },

        deleteCheckedItems(checkedItems) {
            const ids = Object.keys(checkedItems);
            this.landingPageRepository.syncDeleted(ids, Shopware.Context.api).then(() => {
                ids.forEach(id => this.removeFromStore(id));
            });
        },

        onDeleteLandingPage({ data: landingPage }) {
            if (landingPage.isNew()) {
                this.$delete(this.loadedLandingPages, landingPage.id);
                return Promise.resolve();
            }

            return this.landingPageRepository.delete(landingPage.id, Shopware.Context.api).then(() => {
                this.removeFromStore(landingPage.id);

                if (landingPage.id === this.landingPageId) {
                    this.$router.push({ name: 'sw.category.index' });
                }
            });
        },

        changeLandingPage(landingPage) {
            const route = { name: 'sw.category.landingPageDetail', params: { id: landingPage.id } };

            if (this.landingPage && this.landingPageRepository.hasChanges(this.landingPage)) {
                this.$emit('unsaved-changes', route);
            } else {
                this.$router.push(route);
            }
        },

        createNewElement(contextItem, parentId, name = '') {
            const newLandingPage = this.createNewLandingPage(name);
            this.addLandingPage(newLandingPage);
            return newLandingPage;
        },

        syncLandingPages() {
            return this.landingPageRepository.sync(this.landingPages, Shopware.Context.api);
        },

        createNewLandingPage(name) {
            const newLandingPage = this.landingPageRepository.create(Shopware.Context.api);

            newLandingPage.name = name;
            newLandingPage.active = false;

            newLandingPage.save = () => {
                return this.landingPageRepository.save(newLandingPage, Shopware.Context.api).then(() => {
                    const criteria = new Criteria();
                    criteria.setIds([newLandingPage.id].filter((id) => id !== null));
                    this.landingPageRepository.search(criteria, Shopware.Context.api).then((landingPages) => {
                        this.addLandingPages(landingPages);
                    });
                });
            };

            return newLandingPage;
        },

        addLandingPage(landingPage) {
            if (!landingPage) {
                return;
            }

            this.$set(this.loadedLandingPages, landingPage.id, landingPage);
        },

        addLandingPages(landingPages) {
            landingPages.forEach((landingPage) => {
                this.$set(this.loadedLandingPages, landingPage.id, landingPage);
            });
        },

        removeFromStore(id) {
            this.$delete(this.loadedLandingPages, id);
        },

        getLandingPageUrl(landingPage) {
            return this.$router.resolve({
                name: this.linkContext,
                params: { id: landingPage.id }
            }).href;
        }
    }
});
