import { Component, State, Application } from 'src/core/shopware';
import cmsService from 'src/module/sw-cms/service/cms.service';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-cms-detail.html.twig';
import './sw-cms-detail.scss';

Component.register('sw-cms-detail', {
    template,

    inject: ['loginService'],

    data() {
        return {
            pageId: null,
            isLoading: true,
            page: {
                isLoading: false,
                elements: []
            },
            salesChannels: [],
            currentSalesChannelKey: null,
            currentBlock: null,
            blockConfigDefaults: {
                marginBottom: '40px',
                marginTop: '40px',
                sizingMode: 'boxed'
            }
        };
    },

    computed: {
        pageStore() {
            return State.getStore('cms_page');
        },

        salesChannelStore() {
            return State.getStore('sales_channel');
        },

        cmsBlocks() {
            return cmsService.getCmsBlockRegistry();
        },

        cmsElements() {
            return cmsService.getCmsElementRegistry();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.pageId = this.$route.params.id;

                this.salesChannelStore.getList({ page: 1, limit: 25 }).then((response) => {
                    this.salesChannels = response.items;

                    if (this.salesChannels.length > 0) {
                        this.currentSalesChannelKey = this.salesChannels[0].id;
                        this.loadPage(this.pageId);
                    }
                });
            }
        },

        loadPage(pageId) {
            this.isLoading = true;

            const initContainer = Application.getContainer('init');
            const httpClient = initContainer.httpClient;

            httpClient.get(`/_storefront-proxy/${this.currentSalesChannelKey}/storefront-api/v1/cms-page/${pageId}`, {
                headers: { Authorization: `Bearer ${this.loginService.getToken()}` }
            }).then((response) => {
                if (response.data.data) {
                    this.page = this.pageStore.create(response.data.data.id);
                    this.page.setData(response.data.data, false, true, true);

                    this.page.blocks.forEach((block, index) => {
                        block.position = index;
                    });

                    this.isLoading = false;
                }
            });
        },

        loadPageFromApi(pageId) {
            this.isLoading = true;
            const params = {
                criteria: CriteriaFactory.equals('cms_page.id', pageId),
                associations: {
                    blocks: {
                        limit: 500,
                        associations: {
                            slots: { limit: 500 }
                        }
                    }
                }
            };

            return this.pageStore.getList(params, true).then((response) => {
                const cmsPages = response.items;
                this.page = cmsPages[0];
                this.isLoading = false;
            });
        },

        onChangeLanguage() {
            return this.loadPage(this.pageId);
        },

        abortOnLanguageChange() {
            return this.page.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onSalesChannelChange() {
            return this.loadPage(this.pageId);
        },

        onAddBlockSection() {
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

        onBlockStageDrop(dragData, dropData) {
            if (!dropData || !dragData.block || dropData.dropIndex < 0) {
                return;
            }

            const blockStore = this.page.getAssociation('blocks');
            const newBlock = blockStore.create();
            newBlock.type = dragData.block.name;
            newBlock.position = dropData.dropIndex;
            newBlock.pageId = this.page.id;

            Object.assign(newBlock.config, this.blockConfigDefaults);

            const slotStore = newBlock.getAssociation('slots');
            const blockConfig = this.cmsBlocks[newBlock.type];
            Object.keys(blockConfig.slots).forEach((slotName) => {
                const element = slotStore.create();
                element.blockId = newBlock.id;
                element.slot = slotName;
                element.type = blockConfig.slots[slotName];

                newBlock.slots.push(element);
            });

            this.page.blocks.splice(dropData.dropIndex, 0, newBlock);
            this.updateBlockPositions();
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
            this.isLoading = true;
            return this.page.save(true).then(() => {
                this.isLoading = false;
            });
        },

        updateBlockPositions() {
            this.page.blocks.forEach((block, index) => {
                block.position = index;
            });
        }
    }
});
