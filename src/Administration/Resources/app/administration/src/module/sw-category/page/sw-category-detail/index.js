import pageState from './state';
import template from './sw-category-detail.html.twig';
import './sw-category-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria, ChangesetGenerator } = Shopware.Data;
const { cloneDeep, merge } = Shopware.Utils.object;
const type = Shopware.Utils.types;

Component.register('sw-category-detail', {
    template,

    inject: [
        'acl',
        'cmsPageService',
        'cmsService',
        'repositoryFactory',
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
            isCustomFieldLoading: false,
            isSaveSuccessful: false,
            isMobileViewport: null,
            splitBreakpoint: 1024,
            isDisplayingLeavePageWarning: false,
            nextRoute: null,
            currentLanguageId: Shopware.Context.api.languageId,
            discardChanges: false
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
            if (!Shopware.State.get('swCategoryDetail')) {
                return {};
            }

            return Shopware.State.get('swCategoryDetail').category;
        },

        cmsPage() {
            return Shopware.State.get('cmsPageState').currentPage;
        },

        cmsPageId() {
            return this.category ? this.category.cmsPageId : null;
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 100);

            criteria.addFilter(Criteria.equals('relations.entityName', 'category'));
            criteria
                .getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            return criteria;
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        pageClasses() {
            return {
                'has--category': !!this.category,
                'is--mobile': !!this.isMobileViewport
            };
        },

        tooltipSave() {
            if (!this.acl.can('category.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('category.editor'),
                    showOnDisabledElements: true
                };
            }

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
                Shopware.State.dispatch('cmsPageState/resetCmsPageState')
                    .then(this.getAssignedCmsPage);
            }
        }
    },

    beforeCreate() {
        Shopware.State.registerModule('swCategoryDetail', pageState);
        Shopware.State.dispatch('cmsPageState/resetCmsPageState');
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        Shopware.State.unregisterModule('swCategoryDetail');
    },

    beforeRouteLeave(to, from, next) {
        if (this.forceDiscardChanges) {
            this.forceDiscardChanges = false;
            next();

            return;
        }

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

            return this.cmsPageRepository.search(criteria, Shopware.Context.api).then((response) => {
                const cmsPage = response.get(this.cmsPageId);
                if (this.category.slotConfig !== null) {
                    cmsPage.sections.forEach((section) => {
                        section.blocks.forEach((block) => {
                            block.slots.forEach((slot) => {
                                if (this.category.slotConfig[slot.id]) {
                                    if (slot.config === null) {
                                        slot.config = {};
                                    }
                                    merge(slot.config, cloneDeep(this.category.slotConfig[slot.id]));
                                }
                            });
                        });
                    });
                }

                this.updateCmsPageDataMapping();
                Shopware.State.commit('cmsPageState/setCurrentPage', cmsPage);
                return this.cmsPage;
            });
        },

        updateCmsPageDataMapping() {
            Shopware.State.commit('cmsPageState/setCurrentMappingEntity', 'category');
            Shopware.State.commit(
                'cmsPageState/setCurrentMappingTypes',
                this.cmsService.getEntityMappingTypes('category')
            );
            Shopware.State.commit('cmsPageState/setCurrentDemoEntity', this.category);
        },

        setCategory() {
            this.isLoading = true;

            if (this.categoryId === null) {
                return Shopware.State.dispatch('swCategoryDetail/setActiveCategory', { category: null })
                    .then(() => Shopware.State.dispatch('cmsPageState/resetCmsPageState'))
                    .then(() => {
                        this.isLoading = false;
                    });
            }

            return Shopware.State.dispatch('swCategoryDetail/loadActiveCategory', {
                repository: this.categoryRepository,
                apiContext: Shopware.Context.api,
                id: this.categoryId
            }).then(() => Shopware.State.dispatch('cmsPageState/resetCmsPageState'))
                .then(this.getAssignedCmsPage)
                .then(this.loadCustomFieldSet)
                .then(() => {
                    this.isLoading = false;
                });
        },

        loadCustomFieldSet() {
            this.isCustomFieldLoading = true;

            return this.customFieldSetRepository.search(this.customFieldSetCriteria, Shopware.Context.api)
                .then((customFieldSet) => {
                    return this.$store.commit('swCategoryDetail/setCustomFieldSets', customFieldSet);
                }).then(() => {
                    this.isCustomFieldLoading = true;
                });
        },

        onSaveCategories() {
            return this.categoryRepository.save(this.category, Shopware.Context.api);
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
            this.forceDiscardChanges = true;
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
            this.mediaRepository.get(sideBarMedia.id, Shopware.Context.api).then((media) => {
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

            this.isLoading = true;
            this.updateSeoUrls().then(() => {
                return this.categoryRepository.save(this.category, Shopware.Context.api);
            }).then(() => {
                this.isSaveSuccessful = true;
                return this.setCategory();
            }).catch(() => {
                this.isLoading = false;

                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid'
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
        },

        updateSeoUrls() {
            if (!Shopware.State.list().includes('swSeoUrl')) {
                return Promise.resolve();
            }

            const seoUrls = Shopware.State.getters['swSeoUrl/getNewOrModifiedUrls']();

            return Promise.all(seoUrls.map((seoUrl) => {
                if (seoUrl.seoPathInfo) {
                    seoUrl.isModified = true;
                    return this.seoUrlService.updateCanonicalUrl(seoUrl, seoUrl.languageId);
                }

                return Promise.resolve();
            }));
        }
    }
});
