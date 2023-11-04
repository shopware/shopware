import template from './sw-landing-page-tree.html.twig';
import './sw-landing-page-tree.scss';

const { Criteria } = Shopware.Data;
const { mapState } = Shopware.Component.getComponentHelper();

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'syncService', 'acl'],
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
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        allowCreate: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        allowDelete: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },
    },

    data() {
        return {
            loadedLandingPages: {},
            translationContext: 'sw-landing-page',
            linkContext: 'sw.category.landingPage',
            isLoadingInitialData: true,
        };
    },

    computed: {
        ...mapState('swCategoryDetail', [
            'landingPagesToDelete',
        ]),

        cmsLandingPageCriteria() {
            const criteria = new Criteria(1, 500);
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
        },
    },

    watch: {
        landingPagesToDelete(value) {
            if (value === undefined) {
                return;
            }

            this.$refs.landingPageTree.onDeleteElements(value);

            Shopware.State.commit('swCategoryDetail/setLandingPagesToDelete', {
                landingPagesToDelete: undefined,
            });
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
                    this.$set(this.loadedLandingPages, newLandingPage.id, newLandingPage);
                });
            }
        },

        currentLanguageId() {
            this.isLoadingInitialData = true;
            this.loadedLandingPages = {};

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
                        message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                    });
                })
                .finally(() => {
                    this.isLoadingInitialData = false;
                });
        },

        loadLandingPages() {
            return this.landingPageRepository.search(this.cmsLandingPageCriteria).then((result) => {
                this.addLandingPages(result);
            });
        },

        checkedElementsCount(count) {
            this.$emit('landingPage-checked-elements-count', count);
        },

        deleteCheckedItems(checkedItems) {
            const ids = Object.keys(checkedItems);
            this.landingPageRepository.syncDeleted(ids).then(() => {
                ids.forEach(id => this.removeFromStore(id));
            });
        },

        onDeleteLandingPage({ data: landingPage }) {
            if (landingPage.isNew()) {
                this.$delete(this.loadedLandingPages, landingPage.id);
                return Promise.resolve();
            }

            return this.landingPageRepository.delete(landingPage.id).then(() => {
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

        duplicateElement(contextItem) {
            const behavior = {
                cloneChildren: false,
                overwrites: {
                    name: `${contextItem.data.name} ${this.$tc('global.default.copy')}`,
                    url: `${contextItem.data.url}-${this.$tc('global.default.copy')}`,
                    active: false,
                },
            };

            this.landingPageRepository.clone(contextItem.id, Shopware.Context.api, behavior).then((clone) => {
                const criteria = new Criteria(1, 25);
                criteria.setIds([clone.id]);
                this.landingPageRepository.search(criteria).then((landingPages) => {
                    landingPages.forEach(element => {
                        element.childCount = 0;
                        element.parentId = null;
                    });

                    this.addLandingPages(landingPages);
                });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
            });
        },

        createNewElement(contextItem, parentId, name = '') {
            const newLandingPage = this.createNewLandingPage(name);
            this.addLandingPage(newLandingPage);
            return newLandingPage;
        },

        syncLandingPages() {
            return this.landingPageRepository.sync(this.landingPages);
        },

        createNewLandingPage(name) {
            const newLandingPage = this.landingPageRepository.create();

            newLandingPage.name = name;
            newLandingPage.active = false;

            newLandingPage.save = () => {
                return this.landingPageRepository.save(newLandingPage).then(() => {
                    const criteria = new Criteria(1, 25);
                    criteria.setIds([newLandingPage.id].filter((id) => id !== null));
                    this.landingPageRepository.search(criteria).then((landingPages) => {
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
