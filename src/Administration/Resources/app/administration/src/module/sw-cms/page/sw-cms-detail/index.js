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
        'cmsPageService',
        'cmsService',
        'cmsDataResolverService'
    ],

    mixins: [
        Mixin.getByName('cms-state'),
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
                sections: []
            },
            salesChannels: [],
            isLoading: false,
            isSaveSuccessful: false,
            currentSalesChannelKey: null,
            selectedBlockSectionId: null,
            currentMappingEntity: null,
            currentMappingEntityRepo: null,
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
                page: this.$tc('sw-cms.detail.label.pageTypeShopPage'),
                landingpage: this.$tc('sw-cms.detail.label.pageTypeLandingpage'),
                product_list: this.$tc('sw-cms.detail.label.pageTypeCategory')
                // Will be implemented in the future
                // product_detail: this.$tc('sw-cms.detail.label.pageTypeProduct')
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

            criteria.getAssociation('sections')
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
            Shopware.State.commit('adminMenu/collapseSidebar');

            const isSystemDefaultLanguage = Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId;
            this.$store.commit('cmsPageState/setIsSystemDefaultLanguage', isSystemDefaultLanguage);

            this.resetCmsPageState();

            if (this.$route.params.id) {
                this.pageId = this.$route.params.id;
                this.isLoading = true;
                const defaultStorefrontId = '8A243080F92E4C719546314B577CF82B';

                const criteria = new Criteria();
                criteria.addFilter(
                    Criteria.equals('typeId', defaultStorefrontId)
                );

                this.salesChannelRepository.search(criteria, Shopware.Context.api).then((response) => {
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

            return this.defaultFolderRepository.search(criteria, Shopware.Context.api).then((searchResult) => {
                const defaultFolder = searchResult.first();
                if (defaultFolder.folder && defaultFolder.folder.id) {
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

            this.pageRepository.get(pageId, Shopware.Context.api, this.loadPageCriteria).then((page) => {
                this.page = { sections: [] };
                this.page = page;

                this.cmsDataResolverService.resolve(this.page).then(() => {
                    this.updateSectionAndBlockPositions();
                    Shopware.State.commit('cmsPageState/setCurrentPage', this.page);

                    this.updateDataMapping();
                    this.pageOrigin = cloneDeep(this.page);

                    this.isLoading = false;
                }).catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        title: exception.message,
                        message: exception.response
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

            this.currentMappingEntityRepo.search(criteria, Shopware.Context.api).then((response) => {
                this.demoEntityId = response[0].id;
                Shopware.State.commit('cmsPageState/setCurrentDemoEntity', response[0]);
            });
        },

        onDeviceViewChange(view) {
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

            return this.salesChannelRepository.search(new Criteria(), Shopware.Context.api).then((response) => {
                this.salesChannels = response;
                const isSystemDefaultLanguage = Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId;
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

            this.currentMappingEntityRepo.get(demoEntityId, Shopware.Context.api).then((entity) => {
                if (!entity) {
                    return;
                }

                if (this.cmsPageState.currentMappingEntity === 'category' && entity.mediaId !== null) {
                    this.repositoryFactory.create('media').get(entity.mediaId, Shopware.Context.api).then((media) => {
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

            const section = this.sectionRepository.create(Shopware.Context.api);
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

        onSave() {
            this.isSaveSuccessful = false;

            if ((this.isSystemDefaultLanguage && !this.page.name) || !this.page.type) {
                this.pageConfigOpen();

                const warningTitle = this.$tc('sw-cms.detail.notification.titleMissingFields');
                const warningMessage = this.$tc('sw-cms.detail.notification.messageMissingFields');
                this.createNotificationError({
                    title: warningTitle,
                    message: warningMessage
                });

                return Promise.reject();
            }

            if (this.page.type === 'product_list') {
                let foundListingBlock = false;

                this.page.sections.forEach((section) => {
                    section.blocks.forEach((block) => {
                        if (block.type === 'product-listing') {
                            foundListingBlock = true;
                        }
                    });
                });

                if (!foundListingBlock) {
                    this.createNotificationError({
                        title: this.$tc('sw-cms.detail.notification.titleMissingProductListing'),
                        message: this.$tc('sw-cms.detail.notification.messageMissingProductListing')
                    });

                    this.cmsBlocks['product-listing'].hidden = false;

                    this.pageConfigOpen('blocks');
                    return Promise.reject();
                }
                this.cmsBlocks['product-listing'].hidden = true;
            }

            const sections = this.page.sections;

            if (sections.length < 1) {
                this.createNotificationWarning({
                    title: this.$tc('sw-cms.detail.notification.titleMissingSections'),
                    message: this.$tc('sw-cms.detail.notification.messageMissingSections')
                });

                return Promise.reject();
            }

            if (sections.length === 1 && sections[0].blocks.length === 0) {
                this.createNotificationWarning({
                    title: this.$tc('sw-cms.detail.notification.titleMissingBlocks'),
                    message: this.$tc('sw-cms.detail.notification.messageMissingBlocks')
                });

                this.pageConfigOpen('blocks');
                return Promise.reject();
            }

            let foundEmptyRequiredField = [];

            sections.forEach((section) => {
                section.blocks.forEach((block) => {
                    block.backgroundMedia = null;

                    block.slots.forEach((slot) => {
                        foundEmptyRequiredField.push(...this.checkRequiredSlotConfigField(slot));
                    });
                });
            });

            if (foundEmptyRequiredField.length > 0) {
                const warningTitle = this.$tc('sw-cms.detail.notification.titleMissingBlockFields');
                const warningMessage = this.$tc('sw-cms.detail.notification.messageMissingBlockFields');
                this.createNotificationWarning({
                    title: warningTitle,
                    message: warningMessage
                });

                foundEmptyRequiredField = [];
                return Promise.reject();
            }

            this.deleteEntityAndRequiredConfigKey(this.page.sections);

            this.isLoading = true;

            return this.pageRepository.save(this.page, Shopware.Context.api, false).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;

                return this.loadPage(this.page.id);
            }).catch((exception) => {
                this.isLoading = false;

                const errorNotificationTitle = this.$tc('sw-cms.detail.notification.titlePageError');
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
                    const warningTitle = this.$tc('sw-cms.detail.notification.titleMissingElements');
                    const warningMessage = this.$tc('sw-cms.detail.notificationM.messageMissingElements');
                    this.createNotificationWarning({
                        title: warningTitle,
                        message: warningMessage,
                        duration: 10000
                    });

                    this.$store.commit('cmsPageState/removeSelectedItem');
                    this.pageConfigOpen();
                }

                return Promise.reject(exception);
            });
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
                const listingBlock = this.blockRepository.create();
                const blockConfig = this.cmsBlocks['product-listing'];

                listingBlock.type = 'product-listing';
                listingBlock.position = 0;

                listingBlock.sectionId = this.page.sections[0].id;
                listingBlock.setionPosition = 'main';

                Object.assign(
                    listingBlock,
                    cloneDeep(this.blockConfigDefaults),
                    cloneDeep(blockConfig.defaultConfig || {})
                );

                const listingEl = this.slotRepository.create();
                listingEl.blockId = listingBlock.id;
                listingEl.slot = 'content';
                listingEl.type = 'product-listing';
                listingEl.config = {};

                listingBlock.slots.push(listingEl);

                this.page.sections[0].blocks.splice(0, 0, listingBlock);
            } else {
                this.page.sections.forEach((section) => {
                    section.blocks.forEach((block) => {
                        if (block.type === 'product-listing') {
                            section.blocks.remove(block.id);
                        }
                    });
                });
            }

            this.checkSlotMappings();
            this.onPageUpdate();
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
        }
    }
});
