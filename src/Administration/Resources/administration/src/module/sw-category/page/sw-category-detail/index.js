import pageState from './state';
import template from './sw-category-detail.html.twig';
import './sw-category-detail.scss';

const { Component, Mixin, StateDeprecated } = Shopware;
const { Criteria, ChangesetGenerator } = Shopware.Data;
const { cloneDeep, merge } = Shopware.Utils.object;
const type = Shopware.Utils.types;

Component.register('sw-category-detail', {
    template,

    inject: [
        'cmsPageService',
        'cmsService',
        'repositoryFactory',
        'apiContext',
        'seoUrlService'
    ],

    provide() {
        return {
            openMediaSidebar: this.openMediaSidebar
        };
    },

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'cancelEdit'
    },

    props: {
        categoryId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            term: '',
            isLoading: false,
            isSaveSuccessful: false,
            isMobileViewport: null,
            splitBreakpoint: 1024,
            isDisplayingLeavePageWarning: false,
            nextRoute: null,
            currentLanguageId: this.apiContext.languageId
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.category ? this.placeholder(this.category, 'name') : '';
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        cmsPageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        category() {
            return this.$store.state.swCategoryDetail.category;
        },

        cmsPage() {
            return this.$store.state.cmsPageState.currentPage;
        },

        cmsPageId() {
            return this.category ? this.category.cmsPageId : null;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        languageStore() {
            return StateDeprecated.getStore('language');
        },

        pageClasses() {
            return {
                'has--category': !!this.category,
                'is--mobile': !!this.isMobileViewport
            };
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        }
    },

    watch: {
        categoryId() {
            this.setCategory();
        },

        cmsPageId() {
            if (!this.isLoading) {
                this.category.slotConfig = null;
                this.$store.dispatch('cmsPageState/resetCmsPageState')
                    .then(this.getAssignedCmsPage);
            }
        }
    },

    beforeCreate() {
        this.$store.registerModule('swCategoryDetail', pageState);
        this.$store.dispatch('cmsPageState/resetCmsPageState');
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.$store.unregisterModule('swCategoryDetail');
    },

    beforeRouteLeave(to, from, next) {
        if (this.category && this.categoryRepository.hasChanges(this.category)) {
            this.isDisplayingLeavePageWarning = true;
            this.nextRoute = to;
            next(false);
        } else {
            next();
        }
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.checkViewport();
            this.registerListener();

            this.setCategory();
        },

        registerListener() {
            this.$device.onResize({
                listener: this.checkViewport
            });
        },

        onSearch(value) {
            if (value.length === 0) {
                value = undefined;
            }
            this.term = value;
        },

        checkViewport() {
            this.isMobileViewport = this.$device.getViewportWidth() < this.splitBreakpoint;
        },

        getAssignedCmsPage() {
            if (this.cmsPageId === null) {
                return Promise.resolve(null);
            }

            const criteria = new Criteria(1, 1);
            criteria.setIds([this.cmsPageId]);
            criteria.addAssociation('previewMedia');
            criteria.addAssociation('sections');
            criteria.getAssociation('sections').addSorting(Criteria.sort('position'));

            criteria.addAssociation('sections.blocks');
            criteria.getAssociation('sections.blocks')
                .addSorting(Criteria.sort('position', 'ASC'))
                .addAssociation('slots');

            return this.cmsPageRepository.search(criteria, this.apiContext).then((response) => {
                const cmsPage = response.get(this.cmsPageId);
                if (this.category.slotConfig !== null) {
                    cmsPage.sections.forEach((section) => {
                        section.blocks.forEach((block) => {
                            block.slots.forEach((slot) => {
                                if (this.category.slotConfig[slot.id]) {
                                    merge(slot.config, cloneDeep(this.category.slotConfig[slot.id]));
                                }
                            });
                        });
                    });
                }

                this.updateCmsPageDataMapping();
                this.$store.commit('cmsPageState/setCurrentPage', cmsPage);
                return this.cmsPage;
            });
        },

        updateCmsPageDataMapping() {
            this.$store.commit('cmsPageState/setCurrentMappingEntity', 'category');
            this.$store.commit(
                'cmsPageState/setCurrentMappingTypes',
                this.cmsService.getEntityMappingTypes('category')
            );
            this.$store.commit('cmsPageState/setCurrentDemoEntity', this.category);
        },

        setCategory() {
            this.isLoading = true;

            if (this.categoryId === null) {
                return this.$store.dispatch('swCategoryDetail/setActiveCategory', { category: null })
                    .then(() => this.$store.dispatch('cmsPageState/resetCmsPageState'))
                    .then(() => {
                        this.isLoading = false;
                    });
            }

            return this.$store.dispatch('swCategoryDetail/loadActiveCategory', {
                repository: this.categoryRepository,
                apiContext: this.apiContext,
                id: this.categoryId
            }).then(() => this.$store.dispatch('cmsPageState/resetCmsPageState'))
                .then(this.getAssignedCmsPage)
                .then(() => {
                    this.isLoading = false;
                });
        },

        onSaveCategories() {
            return this.categoryRepository.save(this.category, this.apiContext);
        },

        openChangeModal(destination) {
            this.nextRoute = destination;
            this.isDisplayingLeavePageWarning = true;
        },

        onLeaveModalClose() {
            this.nextRoute = null;
            this.isDisplayingLeavePageWarning = false;
        },

        onLeaveModalConfirm(destination) {
            this.isDisplayingLeavePageWarning = false;
            this.$nextTick(() => {
                this.$router.push({ name: destination.name, params: destination.params });
            });
        },

        cancelEdit() {
            this.resetCategory();
        },

        resetCategory() {
            this.$router.push({ name: 'sw.category.index' });
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        setMediaItemFromSidebar(sideBarMedia) {
            // be consistent and fetch from repository
            this.mediaRepository.get(sideBarMedia.id, this.apiContext).then((media) => {
                this.category.mediaId = media.id;
                this.category.media = media;
            });
        },

        onChangeLanguage(newLanguageId) {
            this.currentLanguageId = newLanguageId;
            this.setCategory();
        },

        abortOnLanguageChange() {
            return this.category ? this.categoryRepository.hasChanges(this.category) : false;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;

            const pageOverrides = this.getCmsPageOverrides();

            if (type.isPlainObject(pageOverrides)) {
                this.category.slotConfig = cloneDeep(pageOverrides);
            }

            const seoUrls = this.$store.getters['swSeoUrl/getNewOrModifiedUrls']();

            seoUrls.forEach(seoUrl => {
                if (seoUrl.seoPathInfo) {
                    seoUrl.isModified = true;
                    this.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId);
                }
            });

            this.isLoading = true;
            return this.categoryRepository.save(this.category, this.apiContext).then(() => {
                this.isSaveSuccessful = true;
                return this.setCategory();
            }).catch(() => {
                this.isLoading = false;

                const categoryName = this.category.name || this.category.translated.name;
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessage',
                        0,
                        { entityName: categoryName }
                    )
                });
            });
        },

        getCmsPageOverrides() {
            if (this.cmsPage === null) {
                return null;
            }

            const changesetGenerator = new ChangesetGenerator();
            const { changes } = changesetGenerator.generate(this.cmsPage);

            const slotOverrides = {};
            if (changes === null) {
                return slotOverrides;
            }

            if (type.isArray(changes.sections)) {
                changes.sections.forEach((section) => {
                    if (type.isArray(section.blocks)) {
                        section.blocks.forEach((block) => {
                            if (type.isArray(block.slots)) {
                                block.slots.forEach((slot) => {
                                    if (type.isPlainObject(slot.config)) {
                                        const slotConfig = {};

                                        Object.keys(slot.config).forEach((key) => {
                                            if (slot.config[key].value !== null) {
                                                slotConfig[key] = slot.config[key];
                                            }
                                        });

                                        if (Object.keys(slotConfig).length > 0) {
                                            slotOverrides[slot.id] = slotConfig;
                                        }
                                    }
                                });
                            }
                        });
                    }
                });
            }
            return slotOverrides;
        }
    }
});
