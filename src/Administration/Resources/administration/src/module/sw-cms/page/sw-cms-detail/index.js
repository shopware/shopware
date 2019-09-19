import template from './sw-cms-detail.html.twig';
import './sw-cms-detail.scss';

const { Component, Mixin } = Shopware;
const { cloneDeep, getObjectDiff } = Shopware.Utils.object;
const { warn } = Shopware.Utils.debug;
const types = Shopware.Utils.types;
const Criteria = Shopware.Data.Criteria;

Component.register('sw-cms-detail', {
    template,

    inject: [
        'repositoryFactory',
        'entityFactory',
        'entityHydrator',
        'loginService',
        'cmsPageService',
        'cmsService',
        'cmsDataResolverService',
        'context'
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave'
    },

    data() {
        return {
            pageId: null,
            pageOrigin: null,
            page: {
                blocks: []
            },
            cmsPageState: this.$store.state.cmsPageState,
            salesChannels: [],
            isLoading: false,
            isSaveSuccessful: false,
            currentSalesChannelKey: null,
            currentDeviceView: 'desktop',
            currentBlock: null,
            currentBlockCategory: 'text',
            currentMappingEntity: null,
            currentMappingEntityRepo: null,
            demoEntityId: null,
            currentLanguageId: this.context.languageId
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.page, 'name');
        },

        pageRepository() {
            return this.repositoryFactory.create('cms_page');
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

        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        },

        cmsBlockCategories() {
            const categories = [];

            this.cmsBlocks.forEach((block) => {
                if (!categories.includes(block.category)) {
                    categories.push(block.category);
                }
            });

            return categories;
        },

        cmsStageClasses() {
            return [
                `is--${this.currentDeviceView}`
            ];
        },

        cmsTypeMappingEntities() {
            return {
                product_detail: {
                    entity: 'product',
                    mode: 'single'
                },
                product_list: {
                    entity: 'category',
                    mode: 'single'
                }
            };
        },

        cmsPageTypes() {
            return {
                page: this.$tc('sw-cms.detail.labelPageTypeShopPage'),
                landingpage: this.$tc('sw-cms.detail.labelPageTypeLandingpage'),
                product_list: this.$tc('sw-cms.detail.labelPageTypeCategory'),
                product_detail: this.$tc('sw-cms.detail.labelPageTypeProduct')
            };
        },

        cmsPageTypeSettings() {
            if (this.cmsTypeMappingEntities[this.page.type]) {
                return this.cmsTypeMappingEntities[this.page.type];
            }

            return {
                entity: null,
                mode: 'static'
            };
        },

        blockConfigDefaults() {
            return {
                name: null,
                marginBottom: null,
                marginTop: null,
                marginLeft: null,
                marginRight: null,
                sizingMode: 'boxed'
            };
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        isSystemDefaultLanguage() {
            return this.currentLanguageId === this.context.systemLanguageId;
        },

        addBlockTitle() {
            if (!this.isSystemDefaultLanguage) {
                return this.$tc('sw-cms.general.disabledAddingBlocksToolTip');
            }

            return this.$tc('sw-cms.detail.sidebarTitleBlockOverview');
        }
    },

    watch: {
        'page.blocks': {
            handler() {
                if (this.page.blocks.length <= 0) {
                    this.currentBlock = null;
                }
            }
        }
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        this.beforeDestroyedComponent();
    },

    methods: {
        createdComponent() {
            // ToDo: Make the navigation state accessible via global state
            this.$root.$children[0].$children[2].$children[0].isExpanded = false;

            this.resetCmsPageState();

            if (this.$route.params.id) {
                this.pageId = this.$route.params.id;
                this.isLoading = true;
                const defaultStorefrontId = '8A243080F92E4C719546314B577CF82B';

                const criteria = new Criteria();
                criteria.addFilter(
                    Criteria.equals('typeId', defaultStorefrontId)
                );

                this.salesChannelRepository.search(criteria, this.context).then((response) => {
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
                this.$store.commit('cmsPageState/setDefaultMediaFolderId', folderId);
            });
        },

        resetCmsPageState() {
            this.$store.dispatch('cmsPageState/resetCmsPageState');
        },

        getDefaultFolderId() {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('folder');
            criteria.addFilter(Criteria.equals('entity', this.cmsPageState.pageEntityName));

            return this.defaultFolderRepository.search(criteria, this.context).then((searchResult) => {
                const defaultFolder = searchResult.first();
                if (defaultFolder.folder.id) {
                    return defaultFolder.folder.id;
                }

                return null;
            });
        },

        beforeDestroyedComponent() {
            this.$store.commit('cmsPageState/removeCurrentPage');
        },

        loadPage(pageId) {
            this.isLoading = true;
            const criteria = new Criteria(1, 1);

            criteria.getAssociation('blocks')
                .addSorting(Criteria.sort('position', 'ASC', true))
                .addAssociation('slots')
                .addAssociation('backgroundMedia');

            this.pageRepository.get(pageId, this.context, criteria).then((page) => {
                this.page = { blocks: [] };
                this.page = page;

                this.cmsDataResolverService.resolve(this.page).then(() => {
                    this.updateBlockPositions();
                    this.$store.commit('cmsPageState/setCurrentPage', this.page);

                    if (this.currentBlock !== null) {
                        this.currentBlock = this.page.blocks.get(this.currentBlock.id);
                    }

                    this.updateDataMapping();
                    this.pageOrigin = cloneDeep(this.page);

                    this.isLoading = false;
                }).catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        title: exception.message,
                        message: exception.response.statusText
                    });

                    warn(this._name, exception.message, exception.response);
                });
            }).catch((exception) => {
                this.isLoading = false;
                this.createNotificationError({
                    title: exception.message,
                    message: exception.response.statusText
                });

                warn(this._name, exception.message, exception.response);
            });
        },

        updateDataMapping() {
            const mappingEntity = this.cmsPageTypeSettings.entity;

            if (!mappingEntity) {
                this.$store.commit('cmsPageState/removeCurrentMappingEntity');
                this.$store.commit('cmsPageState/removeCurrentMappingTypes');
                this.$store.commit('cmsPageState/removeCurrentDemoEntity');

                this.currentMappingEntity = null;
                this.currentMappingEntityRepo = null;
                this.demoEntityId = null;
                return;
            }

            if (this.cmsPageState.currentMappingEntity !== mappingEntity) {
                this.$store.commit('cmsPageState/setCurrentMappingEntity', mappingEntity);
                this.$store.commit(
                    'cmsPageState/setCurrentMappingTypes',
                    this.cmsService.getEntityMappingTypes(mappingEntity)
                );

                this.currentMappingEntity = mappingEntity;
                this.currentMappingEntityRepo = this.repositoryFactory.create(mappingEntity);

                this.loadFirstDemoEntity();
            }
        },

        loadFirstDemoEntity() {
            const criteria = new Criteria();

            if (this.cmsPageState.currentMappingEntity === 'category') {
                criteria.addAssociation('media');
            }

            this.currentMappingEntityRepo.search(criteria, this.context).then((response) => {
                this.demoEntityId = response[0].id;
                this.$store.commit('cmsPageState/setCurrentDemoEntity', response[0]);
            });
        },

        onDeviceViewChange(view) {
            this.currentDeviceView = view;

            this.$store.commit('cmsPageState/setCurrentCmsDeviceView', view);

            if (view === 'form') {
                this.currentBlock = null;
                this.$refs.blockConfigSidebar.closeContent();
                this.$refs.blockSelectionSidebar.closeContent();
            }
        },

        onChangeLanguage() {
            this.isLoading = true;

            return this.salesChannelRepository.search(new Criteria(), this.context).then((response) => {
                this.salesChannels = response;
                this.currentLanguageId = this.context.languageId;
                return this.loadPage(this.pageId);
            });
        },

        abortOnLanguageChange() {
            return Object.keys(getObjectDiff(this.page, this.pageOrigin)).length > 0;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onSalesChannelChange() {
            this.loadPage(this.pageId);
        },

        onPageTypeChange() {
            if (this.page.type === 'product_list') {
                const listingBlock = this.blockRepository.create();
                const blockConfig = this.cmsBlocks['product-listing'];

                listingBlock.type = 'product-listing';
                listingBlock.position = 0;
                listingBlock.pageId = this.page.id;

                Object.assign(
                    listingBlock,
                    cloneDeep(this.blockConfigDefaults),
                    cloneDeep(blockConfig.defaultConfig || {})
                );

                const listingEl = this.slotRepository.create();
                listingEl.blockId = listingBlock.id;
                listingEl.slot = 'content';
                listingEl.type = 'product-listing';

                listingBlock.slots.push(listingEl);
                this.page.blocks.splice(0, 0, listingBlock);
            } else {
                this.page.blocks.forEach((block) => {
                    if (block.type === 'product-listing') {
                        this.page.blocks.remove(block.id);
                    }
                });
            }

            this.updateBlockPositions();
            this.updateDataMapping();
            this.checkSlotMappings();
        },

        checkSlotMappings() {
            this.page.blocks.forEach((block) => {
                block.slots.forEach((slot) => {
                    if (slot.config) {
                        Object.keys(slot.config).forEach((key) => {
                            if (slot.config[key].source && slot.config[key].source === 'mapped') {
                                const mappingPath = slot.config[key].value.split('.');

                                if (mappingPath[0] !== this.currentMappingEntity) {
                                    slot.config[key].value = null;
                                    slot.config[key].source = 'static';
                                }
                            }
                        });
                    }
                });
            });
        },

        onDemoEntityChange(demoEntityId) {
            this.$store.commit('cmsPageState/removeCurrentDemoEntity');

            if (!demoEntityId) {
                return;
            }

            const demoEntity = this.currentMappingEntityRepo.get(demoEntityId, this.context);

            if (!demoEntity) {
                return;
            }

            if (this.cmsPageState.currentMappingEntity === 'category' && demoEntity.mediaId !== null) {
                this.repositoryFactory.create('media').get(demoEntity.mediaId, this.context).then((media) => {
                    demoEntity.media = media;
                });
            }

            this.$store.commit('cmsPageState/setCurrentDemoEntity', demoEntity);
        },

        onAddBlockSection() {
            this.currentBlock = null;
            this.$refs.blockSelectionSidebar.openContent();
        },

        onBlockSelection(block) {
            this.currentBlock = block;
            this.$refs.blockConfigSidebar.openContent();
        },

        onCloseBlockConfig() {
            this.currentBlock = null;
        },

        onBlockDelete(blockId) {
            this.page.blocks.remove(blockId);

            if (this.currentBlock && this.currentBlock.id === blockId) {
                this.currentBlock = null;
            }

            this.updateBlockPositions();
        },

        onBlockDuplicate(block) {
            const newBlock = this.blockRepository.create();

            const blockClone = cloneDeep(block);
            blockClone.id = newBlock.id;
            blockClone.position = block.position + 1;
            blockClone.pageId = this.page.id;
            blockClone.slots = [];

            Object.assign(newBlock, blockClone);

            block.slots.forEach((slot) => {
                const element = this.slotRepository.create();
                element.blockId = newBlock.id;
                element.slot = slot.slot;
                element.type = slot.type;
                element.config = cloneDeep(slot.config);
                element.data = cloneDeep(slot.data);

                newBlock.slots.push(element);
            });

            this.page.blocks.splice(newBlock.position, 0, newBlock);
            this.updateBlockPositions();
        },

        onBlockStageDrop(dragData, dropData) {
            if (!dropData || !dragData.block || dropData.dropIndex < 0) {
                return;
            }

            const blockConfig = this.cmsBlocks[dragData.block.name];
            const newBlock = this.blockRepository.create(this.context);
            newBlock.type = dragData.block.name;
            newBlock.position = dropData.dropIndex;
            newBlock.pageId = this.page.id;

            Object.assign(
                newBlock,
                cloneDeep(this.blockConfigDefaults),
                cloneDeep(blockConfig.defaultConfig || {})
            );

            Object.keys(blockConfig.slots).forEach((slotName) => {
                const slotConfig = blockConfig.slots[slotName];
                const element = this.slotRepository.create(this.context);
                element.blockId = newBlock.id;
                element.slot = slotName;

                if (typeof slotConfig === 'string') {
                    element.type = slotConfig;
                } else if (types.isPlainObject(slotConfig)) {
                    element.type = slotConfig.type;

                    if (slotConfig.default && types.isPlainObject(slotConfig.default)) {
                        Object.assign(element, cloneDeep(slotConfig.default));
                    }
                }

                newBlock.slots.add(element);
            });

            this.page.blocks.splice(dropData.dropIndex, 0, newBlock);
            this.updateBlockPositions();

            this.onBlockSelection(newBlock);
        },

        onBlockDragSort(dragData, dropData, validDrop) {
            if (validDrop !== true) {
                return;
            }

            this.page.blocks.moveItem(dragData.block.position, dropData.block.position);

            this.updateBlockPositions();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;

            if ((this.isSystemDefaultLanguage && !this.page.name) || !this.page.type) {
                this.$refs.pageConfigSidebar.openContent();

                const warningTitle = this.$tc('sw-cms.detail.notificationTitleMissingFields');
                const warningMessage = this.$tc('sw-cms.detail.notificationMessageMissingFields');
                this.createNotificationWarning({
                    title: warningTitle,
                    message: warningMessage
                });

                return Promise.reject();
            }

            const blocks = this.page.blocks;
            let foundEmptyRequiredField = [];
            blocks.forEach((block) => {
                block.backgroundMedia = null;

                block.slots.forEach((slot) => {
                    foundEmptyRequiredField.push(...this.checkRequiredSlotConfigField(slot));
                });
            });

            if (foundEmptyRequiredField.length > 0) {
                const warningTitle = this.$tc('sw-cms.detail.notificationTitleMissingBlockFields');
                const warningMessage = this.$tc('sw-cms.detail.notificationMessageMissingBlockFields');
                this.createNotificationWarning({
                    title: warningTitle,
                    message: warningMessage
                });

                foundEmptyRequiredField = [];
                return Promise.reject();
            }

            this.deleteEntityAndRequiredConfigKey(this.page.blocks);

            this.isLoading = true;

            return this.pageRepository.save(this.page, this.context).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;

                return this.loadPage(this.page.id);
            }).catch((exception) => {
                this.isLoading = false;

                const errorNotificationTitle = this.$tc('sw-cms.detail.notificationTitlePageError');
                this.createNotificationError({
                    title: errorNotificationTitle,
                    message: exception.message
                });

                let hasEmptyConfig = false;
                if (exception.response.data && exception.response.data.errors) {
                    exception.response.data.errors.forEach((error) => {
                        if (error.code === 'c1051bb4-d103-4f74-8988-acbcafc7fdc3') {
                            hasEmptyConfig = true;
                        }
                    });
                }

                if (hasEmptyConfig === true) {
                    const warningTitle = this.$tc('sw-cms.detail.notificationTitleMissingElements');
                    const warningMessage = this.$tc('sw-cms.detail.notificationMessageMissingElements');
                    this.createNotificationWarning({
                        title: warningTitle,
                        message: warningMessage,
                        duration: 10000
                    });

                    this.currentDeviceView = 'form';
                    this.currentBlock = null;
                    this.$refs.pageConfigSidebar.openContent();
                }

                return Promise.reject(exception);
            });
        },

        deleteEntityAndRequiredConfigKey(blocks) {
            blocks.forEach((block) => {
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
        },

        checkRequiredSlotConfigField(slot) {
            return Object.values(slot.config).filter((configField) => {
                return !!configField.required &&
                    (configField.value === null || configField.value.length < 1);
            });
        },

        updateBlockPositions() {
            this.page.blocks.forEach((block, index) => {
                block.position = index;
            });
        }
    }
});
