import { Component, State, Application, Mixin } from 'src/core/shopware';
import { cloneDeep } from 'src/core/service/utils/object.utils';
import { warn } from 'src/core/service/utils/debug.utils';
import EntityProxy from 'src/core/data/EntityProxy';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-cms-detail.html.twig';
import './sw-cms-detail.scss';

Component.register('sw-cms-detail', {
    template,

    inject: ['loginService', 'cmsPageService', 'cmsService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    data() {
        return {
            pageId: null,
            page: {
                blocks: []
            },
            salesChannels: [],
            isLoading: false,
            currentSalesChannelKey: null,
            currentDeviceView: 'desktop',
            currentBlock: null,
            currentBlockCategory: 'standard',
            currentSkin: 'default',
            currentMappingEntity: null,
            currentMappingEntityStore: null,
            demoEntityId: null
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

        salesChannelStore() {
            return State.getStore('sales_channel');
        },

        defaultFolderStore() {
            return State.getStore('media_default_folder');
        },

        cmsPageState() {
            return State.getStore('cmsPageState');
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
                }
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
                marginBottom: '40px',
                marginTop: '40px',
                marginLeft: '20px',
                marginRight: '20px',
                sizingMode: 'boxed'
            };
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
            this.cmsPageState.currentPage = null;

            if (this.$route.params.id) {
                this.pageId = this.$route.params.id;
                this.isLoading = true;

                this.salesChannelStore.getList({ page: 1, limit: 25 }).then((response) => {
                    this.salesChannels = response.items;

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
                this.cmsPageState.defaultMediaFolderId = folderId;
            });
        },

        getDefaultFolderId() {
            return this.defaultFolderStore.getList({
                limit: 1,
                criteria: CriteriaFactory.equals('entity', this.cmsPageState.pageEntityName),
                associations: {
                    folder: {}
                }
            }).then(({ items }) => {
                if (items.length !== 1) {
                    return null;
                }

                const defaultFolder = items[0];
                if (defaultFolder.folder.id) {
                    return defaultFolder.folder.id;
                }

                return null;
            });
        },

        beforeDestroyedComponent() {
            this.cmsPageState.currentPage = null;
        },

        loadPage(pageId) {
            this.isLoading = true;

            const initContainer = Application.getContainer('init');
            const httpClient = initContainer.httpClient;
            const currentLanguageId = State.getStore('language').getCurrentId();

            httpClient.get(`/_proxy/sales-channel-api/${this.currentSalesChannelKey}/v1/cms-page/${pageId}`, {
                headers: {
                    Authorization: `Bearer ${this.loginService.getToken()}`,
                    'x-sw-language-id': currentLanguageId
                }
            }).then((response) => {
                if (response.data.data) {
                    this.page = { blocks: [] };
                    this.page = new EntityProxy('cms_page', this.cmsPageService, response.data.data.id, null);
                    this.page.setData(response.data.data, false, true, false, currentLanguageId);

                    this.page.blocks.forEach((block, index) => {
                        block.position = index;

                        if (block.config === null) {
                            block.config = { ...this.blockConfigDefaults };
                        }
                    });

                    this.cmsPageState.currentPage = this.page;

                    this.updateDataMapping();
                    this.isLoading = false;
                }
            }).catch((exception) => {
                this.isLoading = false;

                this.createNotificationError({
                    title: exception.message,
                    message: exception.response.statusText
                });
                warn(this._name, exception.message, exception.response);
                throw exception;
            });
        },

        updateDataMapping() {
            const mappingEntity = this.cmsPageTypeSettings.entity;

            if (!mappingEntity) {
                this.cmsPageState.currentMappingEntity = null;
                this.cmsPageState.currentMappingTypes = {};

                this.currentMappingEntity = null;
                this.currentMappingEntityStore = null;
                return;
            }

            this.cmsPageState.currentMappingEntity = mappingEntity;
            this.cmsPageState.currentMappingTypes = this.cmsService.getEntityMappingTypes(mappingEntity);

            this.currentMappingEntity = mappingEntity;
            this.currentMappingEntityStore = State.getStore(mappingEntity);
        },

        onDeviceViewChange(view) {
            this.currentDeviceView = view;

            if (view === 'form') {
                this.currentBlock = null;
                this.$refs.blockConfigSidebar.closeContent();
                this.$refs.blockSelectionSidebar.closeContent();
            }
        },

        onChangeLanguage() {
            this.isLoading = true;
            return this.salesChannelStore.getList({ page: 1, limit: 25 }).then((response) => {
                this.salesChannels = response.items;
                return this.loadPage(this.pageId);
            });
        },

        abortOnLanguageChange() {
            return this.page.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onSalesChannelChange() {
            this.loadPage(this.pageId);
        },

        onPageTypeChange() {
            this.updateDataMapping();
        },

        onDemoEntityChange(demoEntityId) {
            const demoEntity = this.currentMappingEntityStore.getById(demoEntityId);

            this.cmsPageState.currentDemoEntity = null;

            if (!demoEntity) {
                return;
            }

            this.cmsPageState.currentDemoEntity = demoEntity;
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
            const blockStore = this.page.getAssociation('blocks');
            const block = blockStore.getById(blockId);

            block.delete();

            this.page.blocks.splice(this.page.blocks.findIndex(b => b.id === block.id), 1);
            this.updateBlockPositions();
        },

        onBlockDuplicate(block) {
            const blockStore = this.page.getAssociation('blocks');
            const newBlock = blockStore.create();

            newBlock.type = block.type;
            newBlock.position = block.position + 1;
            newBlock.pageId = this.page.id;

            newBlock.config = cloneDeep(block.config);

            const slotStore = newBlock.getAssociation('slots');
            block.slots.forEach((slot) => {
                const element = slotStore.create();
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
            const blockStore = this.page.getAssociation('blocks');
            const newBlock = blockStore.create();
            newBlock.type = dragData.block.name;
            newBlock.position = dropData.dropIndex;
            newBlock.pageId = this.page.id;

            Object.assign(
                newBlock.config,
                cloneDeep(this.blockConfigDefaults),
                cloneDeep(blockConfig.defaultConfig || {})
            );

            const slotStore = newBlock.getAssociation('slots');
            Object.keys(blockConfig.slots).forEach((slotName) => {
                const slotConfig = blockConfig.slots[slotName];
                const element = slotStore.create();
                element.blockId = newBlock.id;
                element.slot = slotName;

                if (typeof slotConfig === 'string') {
                    element.type = slotConfig;
                } else if (typeof slotConfig === 'object') {
                    element.type = slotConfig.type;

                    if (slotConfig.default && typeof slotConfig.default === 'object') {
                        Object.assign(element, cloneDeep(slotConfig.default));
                    }
                }

                newBlock.slots.push(element);
            });

            this.page.blocks.splice(dropData.dropIndex, 0, newBlock);
            this.updateBlockPositions();

            this.onBlockSelection(newBlock);
        },

        onBlockDragSort(dragData, dropData, validDrop) {
            if (validDrop !== true) {
                return;
            }

            const newIndex = dropData.block.position;
            const oldIndex = dragData.block.position;

            if (newIndex === oldIndex) {
                return;
            }

            const movedItem = this.page.blocks.find((item, index) => index === oldIndex);
            const remainingItems = this.page.blocks.filter((item, index) => index !== oldIndex);
            const sortedItems = [
                ...remainingItems.slice(0, newIndex),
                movedItem,
                ...remainingItems.slice(newIndex)
            ];

            sortedItems.forEach((block, index) => {
                block.position = index;
            });

            this.page.blocks = sortedItems;
        },

        onSave() {
            if (!this.page.name || !this.page.type) {
                this.$refs.pageConfigSidebar.openContent();

                const warningTitle = this.$tc('sw-cms.detail.notificationTitleMissingFields');
                const warningMessage = this.$tc('sw-cms.detail.notificationMessageMissingFields');
                this.createNotificationWarning({
                    title: warningTitle,
                    message: warningMessage
                });
                return Promise.reject();
            }

            this.isLoading = true;
            return this.page.save(true).then(() => {
                return this.loadPage(this.page.id);
            }).catch((exception) => {
                this.isLoading = false;

                this.createNotificationError({
                    title: exception.message,
                    message: exception.response.statusText
                });
            });
        },

        updateBlockPositions() {
            this.page.blocks.forEach((block, index) => {
                block.position = index;
            });
        }
    }
});
