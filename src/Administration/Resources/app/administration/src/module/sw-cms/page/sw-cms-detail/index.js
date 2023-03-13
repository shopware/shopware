import template from './sw-cms-detail.html.twig';
import CMS from '../../constant/sw-cms.constant';
import './sw-cms-detail.scss';

const { Component, Mixin, Utils } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { ShopwareError } = Shopware.Classes;
const { debounce } = Shopware.Utils;
const { cloneDeep, getObjectDiff } = Shopware.Utils.object;
const { warn } = Shopware.Utils.debug;
const { Criteria } = Shopware.Data;
const debounceTimeout = 800;

/**
 * @private
 * @package content
 */
export default {
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
        'systemConfigApiService',
        'cmsPageTypeService',
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
            currentSalesChannelKey: null,
            selectedBlockSectionId: null,
            currentMappingEntity: null,
            currentMappingEntityRepo: null,
            demoEntityId: null,
            validationWarnings: [],
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
            showLayoutSetAsDefaultModal: false,
            isDefaultLayout: false,
            showMissingElementModal: false,
            missingElements: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier),
        };
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

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        cmsStageClasses() {
            return [
                `is--${this.currentDeviceView}`,
            ];
        },

        cmsPageTypeSettings() {
            const mappingEntity = CMS.TYPE_MAPPING_ENTITIES;

            if (mappingEntity.hasOwnProperty(this.page.type)) {
                return mappingEntity[this.page.type];
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
                .getAssociation('sections')
                .addSorting(sortCriteria)
                .addAssociation('backgroundMedia')

                .getAssociation('blocks')
                .addSorting(sortCriteria)
                .addAssociation('backgroundMedia')
                .addAssociation('slots');

            criteria
                .getAssociation('categories')
                .setLimit(25);
            criteria
                .getAssociation('landingPages')
                .setLimit(25);

            criteria.getAssociation('products').setLimit(25);
            criteria.getAssociation('products.manufacturer').setLimit(25);

            return criteria;
        },

        demoProductCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('media');
            criteria.addAssociation('deliveryTime');
            criteria.addAssociation('manufacturer.media');

            return criteria;
        },

        currentDeviceView() {
            return this.cmsPageState.currentCmsDeviceView;
        },

        isProductPage() {
            return this.page.type === CMS.PAGE_TYPES.PRODUCT_DETAIL;
        },

        requiredFieldErrors() {
            return [this.pageNameError].filter(error => !!error);
        },

        pageErrors() {
            return [
                this.requiredFieldErrors.find(error => !!error),
                this.pageSectionsError,
                this.pageBlocksError,
                this.pageSlotsError,
                this.pageSlotConfigError,
            ].filter(error => !!error);
        },

        hasPageErrors() {
            return this.pageErrors.length > 0;
        },

        pageType() {
            this.cmsPageTypeService.getType(this.page.type);
        },

        ...mapPropertyErrors('page', [
            'name',
            'sections',
            'blocks',
            'slots',
            'slotConfig',
        ]),
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyedComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-cms-detail__page',
                path: 'page',
                scope: this,
            });
            Shopware.State.commit('adminMenu/collapseSidebar');

            const isSystemDefaultLanguage = Shopware.State.getters['context/isSystemDefaultLanguage'];
            this.$store.commit('cmsPageState/setIsSystemDefaultLanguage', isSystemDefaultLanguage);

            this.resetCmsPageState();

            if (this.$route.params.id) {
                this.pageId = this.$route.params.id;
                this.isLoading = true;
                const defaultStorefrontId = '8A243080F92E4C719546314B577CF82B';

                Shopware.State.commit('shopwareApps/setSelectedIds', [this.pageId]);

                const criteria = new Criteria(1, 25);
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

            if (this.acl.can('system_config.read')) {
                this.getDefaultLayouts();
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

        loadPage(pageId) {
            this.isLoading = true;

            return this.pageRepository.get(pageId, Shopware.Context.api, this.loadPageCriteria).then((page) => {
                this.page = { sections: [] };
                this.page = page;

                Shopware.State.commit('cmsPageState/setCurrentPageType', page.type);

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

                    Shopware.ExtensionAPI.publishData({
                        id: 'sw-cms-detail__page',
                        path: 'page',
                        scope: this,
                    });

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
                Shopware.State.commit('cmsPageState/removeCurrentDemoProducts');

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

            return this.salesChannelRepository.search(new Criteria(1, 25)).then((response) => {
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

        async loadDemoProduct(entityId) {
            const criteria = Criteria.fromCriteria(this.demoProductCriteria);
            criteria.setLimit(1);

            if (entityId) {
                criteria.setIds([entityId]);
            }

            const context = { ...Shopware.Context.api, inheritance: true };

            const response = await this.productRepository.search(criteria, context);
            Shopware.State.commit('cmsPageState/setCurrentDemoEntity', response[0]);
        },

        async loadDemoCategory(entityId) {
            const criteria = new Criteria(1, 1);

            if (entityId) {
                criteria.setIds([entityId]);
            }

            const response = await this.repositoryFactory.create('category').search(criteria);
            const category = response[0];

            this.demoEntityId = category.id;
            Shopware.State.commit('cmsPageState/setCurrentDemoEntity', category);

            this.loadDemoCategoryProducts(category);

            if (category.mediaId) {
                this.loadDemoCategoryMedia(category);
            }
        },

        async loadDemoCategoryProducts(entity) {
            const productCriteria = Criteria.fromCriteria(this.demoProductCriteria);

            productCriteria.setLimit(8);

            const products = await this.repositoryFactory.create(
                entity.products.entity,
                entity.products.source,
            ).search(productCriteria);

            Shopware.State.commit('cmsPageState/setCurrentDemoProducts', products);
        },

        async loadDemoCategoryMedia(entity) {
            const media = await this.repositoryFactory.create('media').get(entity.mediaId);

            entity.media = media;
            Shopware.State.commit('cmsPageState/setCurrentDemoEntity', entity);
        },

        loadFirstDemoEntity() {
            const demoMappingType = this.cmsPageTypeSettings?.entity;

            if (demoMappingType === 'category') {
                this.loadDemoCategory();
            }
        },

        onDemoEntityChange(demoEntityId) {
            const demoMappingType = this.cmsPageTypeSettings?.entity;

            Shopware.State.commit('cmsPageState/removeCurrentDemoEntity');
            Shopware.State.commit('cmsPageState/removeCurrentDemoProducts');

            if (demoMappingType === 'product') {
                if (demoEntityId) {
                    this.loadDemoProduct(demoEntityId);
                }
                return;
            }

            if (demoMappingType === 'category') {
                this.loadDemoCategory(demoEntityId);
            }
        },

        addFirstSection(type, index) {
            this.onAddSection(type, index);
        },

        addAdditionalSection(type, index) {
            this.onAddSection(type, index);
            this.onSave();
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

            section.visibility = {
                desktop: true,
                tablet: true,
                mobile: true,
            };

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

        onPageSave(debounced = false) {
            this.onPageUpdate();

            if (debounced) {
                this.debouncedPageSave();
                return;
            }

            this.onSave();
        },

        debouncedPageSave: debounce(function debouncedOnSave() {
            this.onSave();
        }, debounceTimeout),

        onSave() {
            this.isSaveSuccessful = false;

            if (!this.pageIsValid()) {
                this.createNotificationError({
                    message: this.$tc('sw-cms.detail.notification.pageInvalid'),
                });

                return Promise.reject();
            }

            return this.onSaveEntity();
        },

        onSaveEntity() {
            this.isLoading = true;
            this.deleteEntityAndRequiredConfigKey(this.page.sections);

            return this.pageRepository.save(this.page, Shopware.Context.api, false).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;

                return this.loadPage(this.page.id);
            }).catch((exception) => {
                this.isLoading = false;

                this.createNotificationError({
                    message: exception.message,
                });

                return Promise.reject(exception);
            });
        },

        addError({
            property = null,
            payload = {},
            code = CMS.REQUIRED_FIELD_ERROR_CODE,
            message = '',
        } = {}) {
            const expression = `cms_page.${this.page.id}.${property}`;
            const error = new ShopwareError({
                code,
                detail: message,
                meta: { parameters: payload },
            });

            Shopware.State.commit('error/addApiError', { expression, error });
        },

        getError(property) {
            return Shopware.State.getters['error/getApiError'](this.page, property);
        },

        getSlotValidations() {
            const uniqueSlotCount = {};
            const requiredMissingSlotConfigs = [];

            this.page.sections.forEach((section) => {
                section.blocks.forEach((block) => {
                    block.backgroundMedia = null;

                    block.slots.forEach((slot) => {
                        if (this.page.type === CMS.PAGE_TYPES.PRODUCT_DETAIL && this.isProductPageElement(slot)) {
                            const camelSlotType = Utils.string.camelCase(slot.type);
                            if (!uniqueSlotCount.hasOwnProperty(camelSlotType)) {
                                uniqueSlotCount[camelSlotType] = {
                                    type: camelSlotType,
                                    count: 1,
                                    blockIds: [block.id],
                                    slotIds: [slot.id],
                                };
                            } else {
                                uniqueSlotCount[camelSlotType].count += 1;
                                uniqueSlotCount[camelSlotType].blockIds.push(block.id);
                                uniqueSlotCount[camelSlotType].slotIds.push(slot.id);
                            }

                            return;
                        }

                        requiredMissingSlotConfigs.push(...this.checkRequiredSlotConfigField(slot, block));
                    });
                });
            });

            return {
                requiredMissingSlotConfigs,
                uniqueSlotCount,
            };
        },

        pageIsValid() {
            if (localStorage.getItem('cmsMissingElementDontRemind') === 'true') {
                this.cmsMissingElementDontRemind = true;
            }

            this.validationWarnings = [];
            Shopware.State.dispatch('error/resetApiErrors');

            const valid = [
                this.missingFieldsValidation(),
                this.listingPageValidation(),
                this.pageSectionCountValidation(),
                this.slotValidation(),
            ].every(validation => validation);

            if (!this.cmsMissingElementDontRemind && valid && this.validationWarnings.length > 0) {
                this.showMissingElementModal = true;
            }

            return valid;
        },

        missingFieldsValidation() {
            const hasName = !this.isSystemDefaultLanguage || this.page.name;
            if (hasName && this.page.type) {
                return true;
            }

            this.addError({
                property: 'name',
                message: this.$tc('sw-cms.detail.notification.messageMissingFields'),
            });

            return false;
        },

        listingPageValidation() {
            if (this.page.type !== CMS.PAGE_TYPES.LISTING) {
                return true;
            }

            const foundListingBlock = this.page.sections.some((section) => {
                return section.blocks.some((block) => {
                    return block.type === 'product-listing';
                });
            });

            if (foundListingBlock) {
                this.cmsBlocks['product-listing'].hidden = true;
                return true;
            }

            this.addError({
                property: 'blocks',
                code: 'listingBlockNotFound',
                message: this.$tc('sw-cms.detail.notification.messageMissingProductListing'),
            });
            this.cmsBlocks['product-listing'].hidden = false;

            return false;
        },

        pageSectionCountValidation() {
            if (this.page.sections.length >= 1) {
                return true;
            }

            this.addError({
                property: 'sections',
                code: 'noSectionsFound',
                message: this.$tc('sw-cms.detail.notification.messageMissingSections'),
            });

            return false;
        },

        slotValidation() {
            let valid = true;
            const { requiredMissingSlotConfigs, uniqueSlotCount } = this.getSlotValidations();
            const affectedErrorElements = [];
            const affectedWarningElements = [];

            if (this.page.type === CMS.PAGE_TYPES.PRODUCT_DETAIL) {
                CMS.UNIQUE_SLOTS.forEach((index) => {
                    if (uniqueSlotCount?.[index]?.count > 1) {
                        uniqueSlotCount[index].label = this.$tc(`sw-cms.elements.${index}.label`);
                        affectedErrorElements.push({ ...uniqueSlotCount[index] });

                        valid = false;
                    } else if (!uniqueSlotCount?.[index]) {
                        affectedWarningElements.push(this.$tc(`sw-cms.elements.${index}.label`));
                    }
                });

                if (affectedErrorElements.length > 0) {
                    const uniqueSlotString = CMS.UNIQUE_SLOTS
                        .map(slot => this.$tc(`sw-cms.elements.${slot}.label`))
                        .join(', ');
                    const message = this.$tc('sw-cms.detail.notification.messageRedundantElements', 0, {
                        names: uniqueSlotString,
                    });

                    this.addError({
                        property: 'slots',
                        code: 'uniqueSlotsOnlyOnce',
                        message,
                        payload: {
                            elements: affectedErrorElements,
                        },
                    });
                }

                if (affectedWarningElements.length > 0) {
                    this.validationWarnings.push(...affectedWarningElements);
                }
            }

            if (requiredMissingSlotConfigs.length > 0) {
                this.addError({
                    property: 'slotConfig',
                    code: 'requiredConfigMissing',
                    message: this.$tc('sw-cms.detail.notification.messageMissingBlockFields'),
                    payload: {
                        elements: requiredMissingSlotConfigs,
                    },
                });

                valid = false;
            }

            return valid;
        },

        deleteEntityAndRequiredConfigKey(sections) {
            sections.forEach((section) => {
                section.blocks.forEach((block) => {
                    block.slots.forEach((slot) => {
                        Object.values(slot.config).forEach((configField) => {
                            if (configField.entity) {
                                delete configField.entity;
                            }
                            if (configField.hasOwnProperty('required')) {
                                delete configField.required;
                            }
                        });
                    });
                });
            });
        },

        checkRequiredSlotConfigField(slot, block) {
            return Object.keys(slot.config).reduce((accumulator, index) => {
                const slotConfig = { ...slot.config[index] };
                if (
                    !!slotConfig.required &&
                    (slotConfig.value === null || slotConfig.value.length < 1)
                ) {
                    slotConfig.name = `${slot.type}.${index}`;
                    slotConfig.blockId = block.id;

                    accumulator.push(slotConfig);
                }

                return accumulator;
            }, []);
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

        async onBlockDuplicate(block, { position: sectionPosition = 0 }) {
            const behavior = {
                overwrites: {
                    position: block.position + 1,
                },
                cloneChildren: true,
            };

            const { id: clonedBlockID } = await this.blockRepository.clone(block.id, Shopware.Context.api, behavior);
            const clonedBlock = await this.blockRepository.get(clonedBlockID);

            const section = this.page.sections[sectionPosition];

            section.blocks.splice(clonedBlock.position, 0, clonedBlock);
            this.updateBlockPositions(section);

            this.onSave();
        },

        async onSectionDuplicate(section) {
            const behavior = {
                overwrites: {
                    position: section.position + 1,
                },
                cloneChildren: true,
            };

            const { id: clonedSectionID } = await this.sectionRepository.clone(section.id, Shopware.Context.api, behavior);
            const clonedSection = await this.sectionRepository.get(clonedSectionID);


            this.page.sections.splice(clonedSection.position, 0, clonedSection);
            this.updateSectionAndBlockPositions(section);

            this.onSave();
        },

        onPageTypeChange(pageType) {
            // if pageType wasn't passed along just assume the page was directly mutated
            if (typeof pageType === 'string') {
                Shopware.State.commit('cmsPageState/setCurrentPageType', pageType);
                this.page.type = pageType;
            }

            if (this.page.type === CMS.PAGE_TYPES.LISTING) {
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

            if (this.page.type === CMS.PAGE_TYPES.PRODUCT_DETAIL) {
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
                            if (slot.config[key]?.source === 'mapped') {
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
            return CMS.UNIQUE_SLOTS_KEBAB.includes(slot.type);
        },

        onOpenLayoutAssignment() {
            this.openLayoutAssignmentModal();
        },

        openLayoutAssignmentModal() {
            this.showLayoutAssignmentModal = true;
        },

        closeLayoutAssignmentModal(saveAfterClose = false) {
            this.showLayoutAssignmentModal = false;
            if (saveAfterClose) {
                this.$nextTick(() => {
                    this.onSaveEntity();
                });
            }
        },

        onOpenLayoutSetAsDefault() {
            this.showLayoutSetAsDefaultModal = true;
        },

        onCloseLayoutSetAsDefault() {
            this.showLayoutSetAsDefaultModal = false;
        },

        async onConfirmLayoutSetAsDefault() {
            let configKey = 'category_cms_page';
            if (this.page.type === 'product_detail') {
                configKey = 'product_cms_page';
            }

            await this.systemConfigApiService.saveValues({
                [`core.cms.default_${configKey}`]: this.pageId,
            });

            this.isDefaultLayout = true;
            this.showLayoutSetAsDefaultModal = false;
        },

        async getDefaultLayouts() {
            const response = await this.systemConfigApiService.getValues('core.cms');
            const productDetailId = response['core.cms.default_category_cms_page'];
            const productListId = response['core.cms.default_product_cms_page'];

            if ([productDetailId, productListId].includes(this.pageId)) {
                this.isDefaultLayout = true;
            }
        },

        onCloseMissingElementModal() {
            this.showMissingElementModal = false;
            this.cmsMissingElementDontRemind = false;

            this.$nextTick(() => {
                this.loadPage(this.pageId);
            });
        },

        onSaveMissingElementModal() {
            if (this.cmsMissingElementDontRemind) {
                localStorage.setItem('cmsMissingElementDontRemind', true);
            }

            this.showMissingElementModal = false;

            this.$nextTick(() => {
                this.onSaveEntity();
            });
        },

        onChangeDontRemindCheckbox() {
            this.cmsMissingElementDontRemind = !this.cmsMissingElementDontRemind;
        },
    },
};
