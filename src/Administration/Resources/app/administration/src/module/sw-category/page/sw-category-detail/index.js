import pageState from './state';
import template from './sw-category-detail.html.twig';
import './sw-category-detail.scss';

const { Component, Context, Mixin } = Shopware;
const { Criteria, ChangesetGenerator, EntityCollection } = Shopware.Data;
const { cloneDeep, merge } = Shopware.Utils.object;
const type = Shopware.Utils.types;

Component.register('sw-category-detail', {
    template,

    inject: [
        'acl',
        'cmsService',
        'repositoryFactory',
        'seoUrlService',
    ],

    provide() {
        return {
            openMediaSidebar: this.openMediaSidebar,
        };
    },

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.acl.can('category.editor');
            },
            method: 'onSave',
        },
        ESCAPE: 'cancelEdit',
    },

    props: {
        categoryId: {
            type: String,
            required: false,
            default: null,
        },
        landingPageId: {
            type: String,
            required: false,
            default: null,
        },
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
            forceDiscardChanges: false,
            categoryCheckedItem: 0,
            landingPageCheckedItem: 0,
            entryPointOverwriteConfirmed: false,
            entryPointOverwriteSalesChannels: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    computed: {
        showEmptyState() {
            return !this.category && !this.landingPage;
        },

        identifier() {
            return this.category ? this.placeholder(this.category, 'name') : '';
        },

        landingPageRepository() {
            return this.repositoryFactory.create('landing_page');
        },

        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        cmsPageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        landingPage() {
            if (!Shopware.State.get('swCategoryDetail')) {
                return {};
            }

            return Shopware.State.get('swCategoryDetail').landingPage;
        },

        category() {
            if (!Shopware.State.get('swCategoryDetail')) {
                return {};
            }

            return Shopware.State.get('swCategoryDetail').category;
        },

        showEntryPointOverwriteModal() {
            return this.entryPointOverwriteSalesChannels !== null && this.entryPointOverwriteSalesChannels.length;
        },

        cmsPage() {
            return Shopware.State.get('cmsPageState').currentPage;
        },

        cmsPageId() {
            if (this.landingPage) {
                return this.landingPage.cmsPageId;
            }

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

        customFieldSetLandingPageCriteria() {
            const criteria = new Criteria(1, 100);

            criteria.addFilter(Criteria.equals('relations.entityName', 'landing_page'));
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
                'is--mobile': !!this.isMobileViewport,
            };
        },

        tooltipSave() {
            if (!this.acl.can('category.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('category.editor'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        landingPageTooltipSave() {
            if (!this.acl.can('landing_page.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('landing_page.editor'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light',
            };
        },

        categoryCriteria() {
            const criteria = new Criteria(1, 1);
            criteria.getAssociation('seoUrls')
                .addFilter(Criteria.equals('isCanonical', true));

            criteria.addAssociation('tags')
                .addAssociation('media')
                .addAssociation('navigationSalesChannels.homeCmsPage.previewMedia')
                .addAssociation('serviceSalesChannels')
                .addAssociation('footerSalesChannels');

            return criteria;
        },

        landingPageCriteria() {
            const criteria = new Criteria(1, 1);

            criteria.addAssociation('tags');
            criteria.addAssociation('salesChannels');

            return criteria;
        },
    },

    watch: {
        landingPageId() {
            this.setLandingPage();
        },

        categoryId() {
            this.setCategory();
        },

        cmsPageId() {
            if (this.isLoading) {
                return;
            }

            if (this.category) {
                this.category.slotConfig = null;
                Shopware.State.dispatch('cmsPageState/resetCmsPageState')
                    .then(this.getAssignedCmsPage);
            }

            if (this.landingPage) {
                this.landingPage.slotConfig = null;
                Shopware.State.dispatch('cmsPageState/resetCmsPageState')
                    .then(this.getAssignedCmsPageForLandingPage);
            }
        },
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

            if (this.categoryId !== null) {
                this.setCategory();

                return;
            }

            this.setLandingPage();
        },

        categoryCheckedElementsCount(count) {
            this.categoryCheckedItem = count;
        },

        landingPageCheckedElementsCount(count) {
            this.landingPageCheckedItem = count;
        },

        registerListener() {
            this.$device.onResize({
                listener: this.checkViewport,
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

            const cmsPageId = this.cmsPageId;
            const criteria = new Criteria(1, 1);
            criteria.setIds([cmsPageId]);
            criteria.addAssociation('previewMedia');
            criteria.addAssociation('sections');
            criteria.getAssociation('sections').addSorting(Criteria.sort('position'));

            criteria.addAssociation('sections.blocks');
            criteria.getAssociation('sections.blocks')
                .addSorting(Criteria.sort('position', 'ASC'))
                .addAssociation('slots');

            return this.cmsPageRepository.search(criteria).then((response) => {
                const cmsPage = response.get(cmsPageId);

                if (cmsPageId !== this.cmsPageId) {
                    return null;
                }

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
                this.cmsService.getEntityMappingTypes('category'),
            );
            Shopware.State.commit('cmsPageState/setCurrentDemoEntity', this.category);
        },

        getAssignedCmsPageForLandingPage() {
            if (this.cmsPageId === null) {
                return Promise.resolve(null);
            }

            const cmsPageId = this.cmsPageId;
            const criteria = new Criteria(1, 1);
            criteria.setIds([cmsPageId]);
            criteria.addAssociation('previewMedia');
            criteria.addAssociation('sections');
            criteria.getAssociation('sections').addSorting(Criteria.sort('position'));

            criteria.addAssociation('sections.blocks');
            criteria.getAssociation('sections.blocks')
                .addSorting(Criteria.sort('position', 'ASC'))
                .addAssociation('slots');

            return this.cmsPageRepository.search(criteria).then((response) => {
                const cmsPage = response.get(cmsPageId);
                if (cmsPageId !== this.cmsPageId) {
                    return null;
                }

                if (this.landingPage.slotConfig !== null) {
                    cmsPage.sections.forEach((section) => {
                        section.blocks.forEach((block) => {
                            block.slots.forEach((slot) => {
                                if (this.landingPage.slotConfig[slot.id]) {
                                    if (slot.config === null) {
                                        slot.config = {};
                                    }
                                    merge(slot.config, cloneDeep(this.landingPage.slotConfig[slot.id]));
                                }
                            });
                        });
                    });
                }

                this.updateCmsPageDataMappingForLandingPage();
                Shopware.State.commit('cmsPageState/setCurrentPage', cmsPage);
                return this.cmsPage;
            });
        },

        updateCmsPageDataMappingForLandingPage() {
            Shopware.State.commit('cmsPageState/setCurrentMappingEntity', 'landing_page');
            Shopware.State.commit(
                'cmsPageState/setCurrentMappingTypes',
                this.cmsService.getEntityMappingTypes('landing_page'),
            );
            Shopware.State.commit('cmsPageState/setCurrentDemoEntity', this.landingPage);
        },

        async setLandingPage() {
            this.isLoading = true;

            try {
                if (this.landingPageId === null) {
                    Shopware.State.commit('shopwareApps/setSelectedIds', []);

                    await Shopware.State.dispatch('swCategoryDetail/setActiveLandingPage', { landingPage: null });
                    await Shopware.State.dispatch('cmsPageState/resetCmsPageState');

                    return;
                }


                await Shopware.State.dispatch('shopwareApps/setSelectedIds', [this.landingPageId]);
                await Shopware.State.dispatch('swCategoryDetail/loadActiveLandingPage', {
                    repository: this.landingPageRepository,
                    apiContext: Shopware.Context.api,
                    id: this.landingPageId,
                    criteria: this.landingPageCriteria,
                });

                await Shopware.State.dispatch('cmsPageState/resetCmsPageState');
                await this.getAssignedCmsPageForLandingPage();
                await this.loadLandingPageCustomFieldSet();
            } catch {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        setCategory() {
            this.isLoading = true;

            if (this.categoryId === null) {
                Shopware.State.commit('shopwareApps/setSelectedIds', []);

                return Shopware.State.dispatch('swCategoryDetail/setActiveCategory', { category: null })
                    .then(() => Shopware.State.dispatch('cmsPageState/resetCmsPageState'))
                    .then(() => {
                        this.isLoading = false;
                    });
            }


            return Shopware.State.dispatch(
                'shopwareApps/setSelectedIds',
                [this.categoryId],
            ).then(() => {
                return Shopware.State.dispatch('swCategoryDetail/loadActiveCategory', {
                    repository: this.categoryRepository,
                    apiContext: Shopware.Context.api,
                    id: this.categoryId,
                    criteria: this.categoryCriteria,
                });
            }).then(() => Shopware.State.dispatch('cmsPageState/resetCmsPageState'))
                .then(this.getAssignedCmsPage)
                .then(this.loadCustomFieldSet)
                .then(() => {
                    this.isLoading = false;
                });
        },

        loadCustomFieldSet() {
            this.isCustomFieldLoading = true;

            return this.customFieldSetRepository.search(this.customFieldSetCriteria)
                .then((customFieldSet) => {
                    return this.$store.commit('swCategoryDetail/setCustomFieldSets', customFieldSet);
                }).finally(() => {
                    this.isCustomFieldLoading = true;
                });
        },

        loadLandingPageCustomFieldSet() {
            this.isCustomFieldLoading = true;

            return this.customFieldSetRepository.search(this.customFieldSetLandingPageCriteria)
                .then((customFieldSet) => {
                    return this.$store.commit('swCategoryDetail/setCustomFieldSets', customFieldSet);
                }).finally(() => {
                    this.isCustomFieldLoading = true;
                });
        },

        onSaveCategories() {
            return this.categoryRepository.save(this.category);
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
            this.mediaRepository.get(sideBarMedia.id).then((media) => {
                this.category.mediaId = media.id;
                this.category.media = media;
            });
        },

        onChangeLanguage(newLanguageId) {
            this.currentLanguageId = newLanguageId;

            if (this.landingPageId !== null) {
                this.setLandingPage();
            }

            this.setCategory();
        },

        abortOnLanguageChange() {
            if (this.landingPage) {
                return this.landingPage ? this.categoryRepository.hasChanges(this.landingPage) : false;
            }

            return this.category ? this.categoryRepository.hasChanges(this.category) : false;
        },

        saveOnLanguageChange() {
            if (this.landingPage) {
                return this.onSaveLandingPage();
            }

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

            if (!this.entryPointOverwriteConfirmed) {
                this.checkForEntryPointOverwrite();
                if (this.showEntryPointOverwriteModal) {
                    return;
                }
            }

            this.isLoading = true;
            this.updateSeoUrls().then(() => {
                return this.categoryRepository.save(this.category, { ...Shopware.Context.api });
            }).then(() => {
                this.isSaveSuccessful = true;
                this.entryPointOverwriteConfirmed = false;
                return this.setCategory();
            }).catch(() => {
                this.isLoading = false;
                this.entryPointOverwriteConfirmed = false;

                this.createNotificationError({
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
                    ),
                });
            });
        },

        checkForEntryPointOverwrite() {
            this.entryPointOverwriteSalesChannels = new EntityCollection('/sales_channel', 'sales_channel', Context.api);

            this.category.navigationSalesChannels.forEach((salesChannel) => {
                if (salesChannel.navigationCategoryId !== null && salesChannel.navigationCategoryId !== this.categoryId) {
                    this.entryPointOverwriteSalesChannels.add(salesChannel);
                }
            });

            this.category.footerSalesChannels.forEach((salesChannel) => {
                if (salesChannel.footerCategoryId !== null && salesChannel.footerCategoryId !== this.categoryId) {
                    this.entryPointOverwriteSalesChannels.add(salesChannel);
                }
            });

            this.category.serviceSalesChannels.forEach((salesChannel) => {
                if (salesChannel.serviceCategoryId !== null && salesChannel.serviceCategoryId !== this.categoryId) {
                    this.entryPointOverwriteSalesChannels.add(salesChannel);
                }
            });
        },

        cancelEntryPointOverwrite() {
            this.entryPointOverwriteSalesChannels = null;
        },

        confirmEntryPointOverwrite() {
            this.entryPointOverwriteSalesChannels = null;
            this.entryPointOverwriteConfirmed = true;
            this.$nextTick(() => {
                this.onSave();
            });
        },

        onSaveLandingPage() {
            this.isSaveSuccessful = false;

            const pageOverrides = this.getCmsPageOverrides();

            if (type.isPlainObject(pageOverrides)) {
                this.landingPage.slotConfig = cloneDeep(pageOverrides);
            }

            if (this.landingPageId !== 'create') {
                if (this.landingPage.salesChannels.length === 0) {
                    this.addLandingPageSalesChannelError();

                    return;
                }
            }

            this.isLoading = true;
            this.landingPageRepository.save(this.landingPage).then(() => {
                this.isSaveSuccessful = true;

                if (this.landingPageId === 'create') {
                    this.$router.push({ name: 'sw.category.landingPageDetail', params: { id: this.landingPage.id } });
                    return Promise.resolve();
                }

                return this.setLandingPage();
            }).catch(() => {
                this.isLoading = false;

                if (this.landingPage.salesChannels.length === 0) {
                    this.addLandingPageSalesChannelError();

                    return;
                }

                this.createNotificationError({
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
                    ),
                });
            });
        },

        addLandingPageSalesChannelError() {
            const shopwareError = new Shopware.Classes.ShopwareError(
                {
                    code: 'landing_page_sales_channel_blank',
                    detail: 'This value should not be blank.',
                    status: '400',
                },
            );

            Shopware.State.dispatch('error/addApiError',
                {
                    expression: `landing_page.${this.landingPage.id}.salesChannels`,
                    error: shopwareError,
                });

            this.createNotificationError({
                message: this.$tc(
                    'global.notification.notificationSaveErrorMessageRequiredFieldsInvalid',
                ),
            });
        },

        getCmsPageOverrides() {
            if (this.cmsPage === null) {
                return null;
            }

            this.deleteSpecifcKeys(this.cmsPage.sections);

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
                                    slotOverrides[slot.id] = slot.config;
                                });
                            }
                        });
                    }
                });
            }

            return slotOverrides;
        },

        deleteSpecifcKeys(sections) {
            if (!sections) {
                return;
            }

            sections.forEach((section) => {
                if (!section.blocks) {
                    return;
                }

                section.blocks.forEach((block) => {
                    if (!block.slots) {
                        return;
                    }

                    block.slots.forEach((slot) => {
                        if (!slot.config) {
                            return;
                        }

                        Object.values(slot.config).forEach((configField) => {
                            if (configField.entity) {
                                delete configField.entity;
                            }
                            if (configField.required) {
                                delete configField.required;
                            }
                            if (configField.type) {
                                delete configField.type;
                            }
                        });
                    });
                });
            });
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
        },

        onLandingPageDelete() {
            Shopware.State.commit('swCategoryDetail/setLandingPagesToDelete', {
                landingPagesToDelete: null,
            });
        },

        onCategoryDelete() {
            Shopware.State.commit('swCategoryDetail/setCategoriesToDelete', {
                categoriesToDelete: null,
            });
        },
    },
});
