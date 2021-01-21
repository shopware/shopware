import template from './sw-landing-page-tree.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-landing-page-tree', {
    template,

    inject: ['repositoryFactory', 'syncService'],
    mixins: [
        Mixin.getByName('notification')
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
            landingPages: [],
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

        landingPageRepository() {
            return this.repositoryFactory.create('landing_page');
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
        currentLanguageId() {
            this.landingPages = [];
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
                this.landingPages = result;
            });
        },

        // Todo: Implement CRUD functions - NEXT-13223
        deleteCheckedItems(checkedItems) {
            const ids = Object.keys(checkedItems);
            this.landingPageRepository.syncDeleted(ids, Shopware.Context.api).then(() => {
                ids.forEach(id => this.removeFromStore(id));
            });
        },

        // Todo: Implement CRUD functions - NEXT-13223
        onDeleteLandingPage({ data: landingPage }) {
            if (landingPage.isNew()) {
                delete this.landingPages[landingPage.id];
                this.landingPages = { ...this.landingPages };
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
            this.$router.push(route);
        },

        // Todo: Implement CRUD functions - NEXT-13223
        createNewElement(contextItem, name = '') {
            const newLandingPage = this.createNewLandingPage(name);
            this.addLandingPage(newLandingPage);
            return newLandingPage;
        },

        // Todo: Implement CRUD functions - NEXT-13223
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

        // Todo: Implement CRUD functions - NEXT-13223
        addLandingPage(landingPage) {
            if (!landingPage) {
                return;
            }

            this.landingPages = { ...this.landingPages, [landingPage.id]: landingPage };
        },

        // Todo: Implement CRUD functions - NEXT-13223
        addLandingPages(landingPages) {
            landingPages.forEach((landingPage) => {
                this.landingPages[landingPage.id] = landingPage;
            });
            this.landingPages = { ...this.landingPages };
        },

        // Todo: Implement CRUD functions - NEXT-13223
        removeFromStore(id) {
            const deletedIds = this.getDeletedIds(id);
            this.loadedParentIds = this.loadedParentIds.filter((loadedId) => {
                return !deletedIds.includes(loadedId);
            });

            deletedIds.forEach((deleted) => {
                delete this.landingPages[deleted];
            });
            this.landingPages = { ...this.landingPages };
        },

        // Todo: Implement CRUD functions - NEXT-13223
        getDeletedIds(idToDelete) {
            const idsToDelete = [idToDelete];
            Object.keys(this.landingPages).forEach((id) => {
                idsToDelete.push(...this.getDeletedIds(id));
            });
            return idsToDelete;
        },

        getLandingPageUrl(landingPage) {
            return this.$router.resolve({
                name: this.linkContext,
                params: { id: landingPage.id }
            }).href;
        }
    }
});
