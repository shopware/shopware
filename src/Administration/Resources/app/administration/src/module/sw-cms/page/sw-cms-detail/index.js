import template from './sw-cms-detail.html.twig';
import './sw-cms-detail.scss';

const { Component, Mixin } = Shopware;
const { cloneDeep, getObjectDiff } = Shopware.Utils.object;
const { warn } = Shopware.Utils.debug;
const Criteria = Shopware.Data.Criteria;

Component.register('sw-cms-detail', {
    template,

    inject: [
        'repositoryFactory',
        'entityFactory',
        'entityHydrator',
        'loginService',
        'cmsService',
        'cmsDataResolverService',
        'acl',
        'appCmsService',
    ],

    mixins: [
        Mixin.getByName('cms-state'),
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
    },

    data() {
        return {
            pageId: null,
            pageOrigin: null,
            page: {
                sections: [],
            },
            salesChannels: [],
            isLoading: false,
            isSaveSuccessful: false,
            isSaveable: false,
            currentSalesChannelKey: null,
            selectedBlockSectionId: null,
            currentMappingEntity: null,
            currentMappingEntityRepo: null,
            demoEntityId: null,
            productDetailBlocks: [
                {
                    type: 'cross-selling',
                    elements: [
                        {
                            slot: 'content',
                            type: 'cross-selling',
                            config: {},
                        },
                    ],
                },
                {
                    type: 'product-description-reviews',
                    elements: [
                        {
                            slot: 'content',
                            type: 'product-description-reviews',
                            config: {},
                        },
                    ],
                },
                {
                    type: 'gallery-buybox',
                    elements: [
                        {
                            slot: 'left',
                            type: 'image-gallery',
                            config: {},
                        },
                        {
                            slot: 'right',
                            type: 'buy-box',
                            config: {},
                        },
                    ],
                },
                {
                    type: 'product-heading',
                    elements: [
                        {
                            slot: 'left',
                            type: 'product-name',
                            config: {},
                        },
                        {
                            slot: 'right',
                            type: 'manufacturer-logo',
                            config: {},
                        },
                    ],
                },
            ],
            showLayoutAssignmentModal: false,
            showMissingElementModal: false,
            missingElements: [],

            /** @deprecated tag:v6.5.0 data prop can be removed completely */
            previousRoute: '',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
    },

    /** @deprecated tag:v6.5.0 navigation guard can be removed completely */
    beforeRouteEnter(to, from, next) {
        next((vm) => {
            vm.previousRoute = from.name;
        });
    },

    computed: {
        identifier() {
            return this.placeholder(this.page, 'name');
        },

        pageRepository() {
            return this.repositoryFactory.create('cms_page');
        },

        sectionRepository() {
            return this.repositoryFactory.create('cms_section');
        },

        blockRepository() {
            return this.repositoryFactory.create('cms_block');
        },

        slotRepository() {
            return this.repositoryFactory.create('cms_slot');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        defaultFolderRepository() {
            return this.repositoryFactory.create('media_default_folder');
        },

        cmsBlocks() {
            return this.cmsService.getCmsBlockRegistry();
        },

        cmsStageClasses() {
            return [
                `is--${this.currentDeviceView}`,
            ];
        },

        cmsTypeMappingEntities() {
            return {
                product_detail: {
                    entity: 'product',
                    mode: 'single',
                },
                product_list: {
                    entity: 'category',
                    mode: 'single',
                },
            };
        },

        cmsPageTypes() {
            return {
                page: this.$tc('sw-cms.detail.label.pageTypeShopPage'),
                landingpage: this.$tc('sw-cms.detail.label.pageTypeLandingpage'),
                product_list: this.$tc('sw-cms.detail.label.pageTypeCategory'),
                product_detail: this.$tc('sw-cms.detail.label.pageTypeProduct'),
            };
        },

        cmsPageTypeSettings() {
            if (this.cmsTypeMappingEntities[this.page.type]) {
                return this.cmsTypeMappingEntities[this.page.type];
            }

            return {
                entity: null,
                mode: 'static',
            };
        },

        blockConfigDefaults() {
            return {
                name: null,
                marginBottom: null,
                marginTop: null,
                marginLeft: null,
                marginRight: null,
                sizingMode: 'boxed',
            };
        },

        tooltipSave() {
            if (!this.acl.can('cms.editor')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('cms.editor'),
                    showOnDisabledElements: true,
                };
            }

            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light',
            };
        },

        addBlockTitle() {
            if (!this.isSystemDefaultLanguage) {
                return this.$tc('sw-cms.general.disabledAddingBlocksToolTip');
            }

            return this.$tc('sw-cms.detail.sidebar.titleBlockOverview');
        },

        pageHasSections() {
            return this.page.sections.length > 0;
        },

        loadPageCriteria() {
            const criteria = new Criteria(1, 1);
            const sortCriteria = Criteria.sort('position', 'ASC', true);

            criteria
                .addAssociation('categories')
                .addAssociation('landingPages')
                .addAssociation('products.manufacturer')
                .getAssociation('sections')
                .addSorting(sortCriteria)
                .addAssociation('backgroundMedia')
                .getAssociation('blocks')
                .addSorting(sortCriteria)
                .addAssociation('backgroundMedia')
                .addAssociation('slots');

            return criteria;
        },

        currentDeviceView() {
            return this.cmsPageState.currentCmsDeviceView;
        },

        demoProductCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('media');
            criteria.addAssociation('deliveryTime');
            criteria.addAssociation('manufacturer.media');

            return criteria;
        },

        isProductPage() {
            return this.page.type === 'product_detail';
        },
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyedComponent();
    },

    methods: {
        createdComponent() {
            Shopware.State.commit('adminMenu/collapseSidebar');

            const isSystemDefaultLanguage = Shopware.State.getters['context/isSystemDefaultLanguage'];
            this.$store.commit('cmsPageState/setIsSystemDefaultLanguage', isSystemDefaultLanguage);

            this.resetCmsPageState();

            if (this.$route.params.id) {
                this.pageId = this.$route.params.id;
                this.isLoading = true;
                const defaultStorefrontId = '8A243080F92E4C719546314B577CF82B';

                const criteria = new Criteria();
                criteria.addFilter(
                    Criteria.equals('typeId', defaultStorefrontId),
                );

                this.salesChannelRepository.search(criteria).then((response) => {
                    this.salesChannels = response;

                    if (this.salesChannels.length > 0) {
                        this.currentSalesChannelKey = this.salesChannels[0].id;
                        this.loadPage(this.pageId);
                    }
                });
            }

            this.setPageContext();
        },

        setPageContext() {
            this.getDefaultFolderId().then((folderId) => {
                Shopware.State.commit('cmsPageState/setDefaultMediaFolderId', folderId);
            });
        },

        resetCmsPageState() {
            Shopware.State.dispatch('cmsPageState/resetCmsPageState');
        },

        getDefaultFolderId() {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('folder');
            criteria.addFilter(Criteria.equals('entity', this.cmsPageState.pageEntityName));

            return this.defaultFolderRepository.search(criteria).then((searchResult) => {
                const defaultFolder = searchResult.first();
                if (defaultFolder.folder?.id) {
                    return defaultFolder.folder.id;
                }

                return null;
            });
        },

        beforeDestroyedComponent() {
            Shopware.State.commit('cmsPageState/removeCurrentPage');
            Shopware.State.commit('cmsPageState/removeSelectedBlock');
            Shopware.State.commit('cmsPageState/removeSelectedSection');
        },

        onBlockNavigatorSort(isCrossSectionMove = false) {
            if (isCrossSectionMove) {
                this.loadPage(this.pageId);
                return;
            }

            this.onPageUpdate();
        },

        loadPage(pageId) {
            this.isLoading = true;

            this.pageRepository.get(pageId, Shopware.Context.api, this.loadPageCriteria).then((page) => {
                this.page = { sections: [] };
                this.page = page;

                this.cmsDataResolverService.resolve(this.page).then(() => {
                    this.updateSectionAndBlockPositions();
                    Shopware.State.commit('cmsPageState/setCurrentPage', this.page);

                    this.updateDataMapping();
                    this.pageOrigin = cloneDeep(this.page);

                    if (this.selectedBlock) {
                        const blockId = this.selectedBlock.id;
                        const blockSectionId = this.selectedBlock.sectionId;
                        this.page.sections.forEach((section) => {
                            if (section.id === blockSectionId) {
                                section.blocks.forEach((block) => {
                                    if (block.id === blockId) {
                                        this.setSelectedBlock(blockSectionId, block);
                                    }
                                });
                            }
                        });
                    }

                    this.isLoading = false;
                }).catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        title: exception.message,
                        message: exception.response,
                    });

                    warn(this._name, exception.message, exception.response);
                });
            }).catch((exception) => {
                this.isLoading = false;
                this.createNotificationError({
                    title: exception.message,
                    message: exception.response.statusText,
                });

                warn(this._name, exception.message, exception.response);
            });
        },

        updateDataMapping() {
            const mappingEntity = this.cmsPageTypeSettings.entity;

            if (!mappingEntity) {
                Shopware.State.commit('cmsPageState/removeCurrentMappingEntity');
                Shopware.State.commit('cmsPageState/removeCurrentMappingTypes');
                Shopware.State.commit('cmsPageState/removeCurrentDemoEntity');

                this.currentMappingEntity = null;
                this.currentMappingEntityRepo = null;
                this.demoEntityId = null;
                return;
            }

            if (this.cmsPageState.currentMappingEntity !== mappingEntity) {
                Shopware.State.commit('cmsPageState/setCurrentMappingEntity', mappingEntity);
                Shopware.State.commit(
                    'cmsPageState/setCurrentMappingTypes',
                    this.cmsService.getEntityMappingTypes(mappingEntity),
                );

                this.currentMappingEntity = mappingEntity;
                this.currentMappingEntityRepo = this.repositoryFactory.create(mappingEntity);

                this.loadFirstDemoEntity();
            }
        },

        loadFirstDemoEntity() {
            if (this.cmsPageState.currentMappingEntity === 'product') {
                return;
            }

            const criteria = new Criteria();

            if (this.cmsPageState.currentMappingEntity === 'category') {
                criteria.addAssociation('media');
            }

            this.currentMappingEntityRepo.search(criteria).then((response) => {
                this.demoEntityId = response[0].id;
                Shopware.State.commit('cmsPageState/setCurrentDemoEntity', response[0]);
            });
        },

        onDeviceViewChange(view) {
            if (view === 'form' && !this.acl.can('cms.editor')) {
                return;
            }

            Shopware.State.commit('cmsPageState/setCurrentCmsDeviceView', view);

            if (view === 'form') {
                this.setSelectedBlock(null, null);
            }
        },

        setSelectedBlock(sectionId, block = null) {
            this.selectedBlockSectionId = sectionId;
            this.$store.dispatch('cmsPageState/setBlock', block);
        },

        onChangeLanguage() {
            this.isLoading = true;

            return this.salesChannelRepository.search(new Criteria()).then((response) => {
                this.salesChannels = response;
                const isSystemDefaultLanguage = Shopware.State.getters['context/isSystemDefaultLanguage'];
                this.$store.commit('cmsPageState/setIsSystemDefaultLanguage', isSystemDefaultLanguage);
                return this.loadPage(this.pageId);
            });
        },

        abortOnLanguageChange() {
            return Object.keys(getObjectDiff(this.page, this.pageOrigin)).length > 0;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onDemoEntityChange(demoEntityId) {
            Shopware.State.commit('cmsPageState/removeCurrentDemoEntity');

            if (!demoEntityId) {
                return;
            }

            const demoContext = this.cmsPageState.currentMappingEntity === 'product'
                ? { ...Shopware.Context.api, inheritance: true }
                : Shopware.Context.api;

            const demoCriteria = this.cmsPageState.currentMappingEntity === 'product'
                ? this.demoProductCriteria : new Criteria();

            this.currentMappingEntityRepo.get(demoEntityId, demoContext, demoCriteria).then((entity) => {
                if (!entity) {
                    return;
                }

                if (this.cmsPageState.currentMappingEntity === 'category' && entity.mediaId !== null) {
                    this.repositoryFactory.create('media').get(entity.mediaId).then((media) => {
                        entity.media = media;
                        Shopware.State.commit('cmsPageState/setCurrentDemoEntity', entity);
                    });
                } else {
                    Shopware.State.commit('cmsPageState/setCurrentDemoEntity', entity);
                }
            });
        },

        onAddSection(type, index) {
            if (!type || index === 'undefined') {
                return;
            }

            const section = this.sectionRepository.create();
            section.type = type;
            section.sizingMode = 'boxed';
            section.position = index;
            section.pageId = this.page.id;

            this.page.sections.splice(index, 0, section);
            this.updateSectionAndBlockPositions();
        },

        onCloseBlockConfig() {
            this.$store.commit('cmsPageState/removeSelectedItem');
        },

        pageConfigOpen(mode = null) {
            const sideBarRefs = this.$refs.cmsSidebar.$refs;

            if (mode === 'blocks') {
                if (!this.isSystemDefaultLanguage) {
                    return;
                }

                sideBarRefs.blockSelectionSidebar.openContent();
                return;
            }

            if (mode === 'itemConfig') {
                sideBarRefs.itemConfigSidebar.openContent();
                return;
            }

            sideBarRefs.pageConfigSidebar.openContent();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSaveEntity() {
            this.isLoading = true;

            return this.pageRepository.save(this.page, Shopware.Context.api, false).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;

                return this.loadPage(this.page.id);
            }).catch((exception) => {
                this.isLoading = false;

                this.createNotificationError({
                    message: exception.message,
                });

                let hasEmptyConfig = false;
                if (exception.response.data?.errors) {
                    exception.response.data.errors.forEach((error) => {
                        if (error.code === 'c1051bb4-d103-4f74-8988-acbcafc7fdc3') {
                            hasEmptyConfig = true;
                        }
                    });
                }

                if (hasEmptyConfig === true) {
                    const warningMessage = this.$tc('sw-cms.detail.notification.messageMissingElements');
                    this.createNotificationError({
                        message: warningMessage,
                        duration: 10000,
                    });

                    this.$store.commit('cmsPageState/removeSelectedItem');
                    this.pageConfigOpen();
                }

                return Promise.reject(exception);
            });
        },

        getSlotValidations(sections) {
            const foundEmptyRequiredField = [];
            const foundProductPageElements = {
                buyBox: 0,
                productDescriptionReviews: 0,
                crossSelling: 0,
            };

            sections.forEach((section) => {
                section.blocks.forEach((block) => {
                    block.backgroundMedia = null;

                    block.slots.forEach((slot) => {
                        if (this.page.type === 'product_detail' && this.isProductPageElement(slot)) {
                            if (slot.type === 'buy-box') {
                                foundProductPageElements.buyBox += 1;
                            } else if (slot.type === 'product-description-reviews') {
                                foundProductPageElements.productDescriptionReviews += 1;
                            } else if (slot.type === 'cross-selling') {
                                foundProductPageElements.crossSelling += 1;
                            }

                            return;
                        }

                        foundEmptyRequiredField.push(...this.checkRequiredSlotConfigField(slot));
                    });
                });
            });

            return {
                foundEmptyRequiredField,
                foundProductPageElements,
            };
        },

        getRedundantElementsWarning(foundProductPageElements) {
            const warningMessages = [];

            Object.entries(foundProductPageElements).forEach(([key, value]) => {
                if (value > 1) {
                    warningMessages.push(
                        this.$tc('sw-cms.detail.notification.messageRedundantElements',
                            0, {
                                name: this.$tc(`sw-cms.elements.${key}.label`),
                            }),
                    );
                }
            });

            return warningMessages;
        },

        getMissingElements(elements) {
            return Object.keys(elements).filter((key) => elements[key] === 0);
        },

        onPageSave() {
            this.onSave();
        },

        onSave() {
            this.isSaveSuccessful = false;

            if ((this.isSystemDefaultLanguage && !this.page.name) || !this.page.type) {
                this.pageConfigOpen();

                const warningMessage = this.$tc('sw-cms.detail.notification.messageMissingFields');
                this.createNotificationError({
                    message: warningMessage,
                });

                return Promise.reject();
            }
            const sections = this.page.sections;

            if (this.page.type === 'product_list') {
                let foundListingBlock = false;

                sections.forEach((section) => {
                    section.blocks.forEach((block) => {
                        if (block.type === 'product-listing') {
                            foundListingBlock = true;
                        }
                    });
                });

                if (!foundListingBlock) {
                    this.createNotificationError({
                        message: this.$tc('sw-cms.detail.notification.messageMissingProductListing'),
                    });

                    this.cmsBlocks['product-listing'].hidden = false;

                    this.pageConfigOpen('blocks');
                    return Promise.reject();
                }
                this.cmsBlocks['product-listing'].hidden = true;
            }


            if (sections.length < 1) {
                this.createNotificationError({
                    message: this.$tc('sw-cms.detail.notification.messageMissingSections'),
                });

                return Promise.reject();
            }

            if (sections.length === 1 && sections[0].blocks.length === 0) {
                this.createNotificationError({
                    message: this.$tc('sw-cms.detail.notification.messageMissingBlocks'),
                });

                this.pageConfigOpen('blocks');
                return Promise.reject();
            }

            const { foundEmptyRequiredField, foundProductPageElements } = this.getSlotValidations(sections);

            if (this.page.type === 'product_detail') {
                const warningMessages = this.getRedundantElementsWarning(foundProductPageElements);

                if (warningMessages.length > 0) {
                    warningMessages.forEach((message) => {
                        this.createNotificationError({
                            message,
                        });
                    });

                    return Promise.reject();
                }
            }

            const missingElements = this.getMissingElements(foundProductPageElements);
            if (this.page.type === 'product_detail' && missingElements.length > 0 && !this.isSaveable) {
                this.missingElements = missingElements;
                this.showMissingElementModal = true;

                return Promise.reject();
            }

            if (foundEmptyRequiredField.length > 0) {
                const warningMessage = this.$tc('sw-cms.detail.notification.messageMissingBlockFields');
                this.createNotificationError({
                    message: warningMessage,
                });

                return Promise.reject();
            }

            this.deleteEntityAndRequiredConfigKey(this.page.sections);

            return this.onSaveEntity();
        },

        deleteEntityAndRequiredConfigKey(sections) {
            sections.forEach((section) => {
                section.blocks.forEach((block) => {
                    block.slots.forEach((slot) => {
                        Object.values(slot.config).forEach((configField) => {
                            if (configField.entity) {
                                delete configField.entity;
                            }
                            if (configField.required) {
                                delete configField.required;
                            }
                        });
                    });
                });
            });
        },

        checkRequiredSlotConfigField(slot) {
            return Object.values(slot.config).filter((configField) => {
                return !!configField.required &&
                    (configField.value === null || configField.value.length < 1);
            });
        },

        updateSectionAndBlockPositions() {
            this.page.sections.forEach((section, index) => {
                section.position = index;
                this.updateBlockPositions(section);
            });
        },

        updateBlockPositions(section) {
            section.blocks.forEach((block, index) => {
                block.position = index;
            });
        },

        onPageUpdate() {
            this.updateSectionAndBlockPositions();
            this.updateDataMapping();
        },

        onBlockDuplicate(block, section) {
            this.cloneBlockInSection(block, section);
            this.updateSectionAndBlockPositions();
        },

        cloneBlockInSection(block, section) {
            const newBlock = this.blockRepository.create();

            const blockClone = cloneDeep(block);
            blockClone.id = newBlock.id;
            blockClone.position = block.position + 1;
            blockClone.sectionId = section.id;
            blockClone.sectionPosition = block.sectionPosition;
            blockClone.slots = [];

            Object.assign(newBlock, blockClone);

            this.cloneSlotsInBlock(block, newBlock);

            section.blocks.splice(newBlock.position, 0, newBlock);
        },

        cloneSlotsInBlock(block, newBlock) {
            block.slots.forEach((slot) => {
                const element = this.slotRepository.create();
                element.blockId = newBlock.id;
                element.slot = slot.slot;
                element.type = slot.type;
                element.config = cloneDeep(slot.config);
                element.data = cloneDeep(slot.data);

                newBlock.slots.push(element);
            });
        },

        onSectionDuplicate(section) {
            const newSection = this.sectionRepository.create();

            const sectionClone = cloneDeep(section);
            sectionClone.id = newSection.id;
            sectionClone.position = section.position + 1;
            sectionClone.pageId = this.page.id;
            sectionClone.blocks = [];

            Object.assign(newSection, sectionClone);

            section.blocks.forEach((block) => {
                this.cloneBlockInSection(block, newSection);
            });

            this.page.sections.splice(newSection.position, 0, newSection);
            this.updateSectionAndBlockPositions();
        },

        onPageTypeChange() {
            if (this.page.type === 'product_list') {
                this.processProductListingType();
            } else {
                this.page.sections.forEach((section) => {
                    section.blocks.forEach((block) => {
                        if (block.type === 'product-listing') {
                            section.blocks.remove(block.id);
                        }
                    });
                });
            }

            if (this.page.type === 'product_detail') {
                this.processProductDetailType();
            }

            this.checkSlotMappings();
            this.onPageUpdate();
        },

        processProductListingType() {
            const listingBlock = this.blockRepository.create();
            const listingElements = [
                {
                    blockId: listingBlock.id,
                    slot: 'content',
                    type: 'product-listing',
                    config: {},
                },
            ];

            this.processBlock(listingBlock, 'product-listing');
            this.processElements(listingBlock, listingElements);
        },

        processProductDetailType() {
            this.productDetailBlocks.forEach(block => {
                const newBlock = this.blockRepository.create();

                block.elements.forEach(el => { el.blockId = newBlock.id; });

                this.processBlock(newBlock, block.type);
                this.processElements(newBlock, block.elements);
            });
        },

        processBlock(block, blockType) {
            const cmsBlock = this.cmsBlocks[blockType];
            let defaultConfig = cmsBlock.defaultConfig;

            if (this.isProductPage && defaultConfig) {
                defaultConfig = {
                    ...defaultConfig,
                    marginLeft: '0',
                    marginRight: '0',
                    marginTop: (blockType === 'gallery-buybox' || blockType === 'product-description-reviews')
                        ? '20px' : '0',
                    marginBottom: (blockType === 'product-heading' || blockType === 'product-description-reviews')
                        ? '20px' : '0',
                };
            }

            block.type = blockType;
            block.position = 0;

            block.sectionId = this.page.sections[0].id;
            block.sectionPosition = 'main';

            Object.assign(
                block,
                cloneDeep(this.blockConfigDefaults),
                cloneDeep(defaultConfig || {}),
            );
        },

        processElements(block, elements) {
            elements.forEach((element) => {
                const slot = this.slotRepository.create();

                slot.blockId = element.blockId;
                slot.slot = element.slot;
                slot.type = element.type;
                slot.config = element.config;

                block.slots.push(slot);
            });

            this.page.sections[0].blocks.splice(0, 0, block);
        },

        checkSlotMappings() {
            this.page.sections.forEach((sections) => {
                sections.blocks.forEach((block) => {
                    block.slots.forEach((slot) => {
                        Object.keys(slot.config).forEach((key) => {
                            if (slot.config[key].source && slot.config[key].source === 'mapped') {
                                const mappingPath = slot.config[key].value.split('.');

                                if (mappingPath[0] !== this.demoEntity) {
                                    slot.config[key].value = null;
                                    slot.config[key].source = 'static';
                                }
                            }
                        });
                    });
                });
            });
        },

        isProductPageElement(slot) {
            return ['buy-box', 'product-description-reviews', 'cross-selling'].includes(slot.type);
        },

        onOpenLayoutAssignment() {
            this.openLayoutAssignmentModal();
        },

        openLayoutAssignmentModal() {
            this.showLayoutAssignmentModal = true;
        },

        closeLayoutAssignmentModal() {
            this.showLayoutAssignmentModal = false;
        },

        /** @deprecated tag:v6.5.0 method can be removed completely */
        onConfirmLayoutAssignment() {
            this.previousRoute = '';
        },

        onCloseMissingElementModal() {
            this.showMissingElementModal = false;
        },

        onSaveMissingElementModal() {
            this.showMissingElementModal = false;
            this.isSaveable = true;

            this.$nextTick(() => {
                this.onSave().finally(() => {
                    this.isSaveable = false;
                });
            });
        },
    },
});
