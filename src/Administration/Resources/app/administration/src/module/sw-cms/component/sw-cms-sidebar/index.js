import template from './sw-cms-sidebar.html.twig';
import './sw-cms-sidebar.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;
const types = Shopware.Utils.types;

Component.register('sw-cms-sidebar', {
    template,

    inject: [
        'cmsService',
        'repositoryFactory',
        'feature',
    ],

    mixins: [
        Mixin.getByName('cms-state'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        page: {
            type: Object,
            required: true,
        },

        demoEntity: {
            type: String,
            required: false,
            default: null,
        },

        demoEntityIdProp: {
            type: String,
            required: false,
            default: null,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            demoEntityId: this.demoEntityIdProp,
            currentBlockCategory: 'text',
            currentDragSectionIndex: null,
            showSidebarNavigatorModal: false,
            navigatorDontRemind: false,
        };
    },

    computed: {
        blockRepository() {
            return this.repositoryFactory.create('cms_block');
        },

        slotRepository() {
            return this.repositoryFactory.create('cms_slot');
        },

        cmsBlocks() {
            return this.cmsService.getCmsBlockRegistry();
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        addBlockTitle() {
            if (!this.isSystemDefaultLanguage) {
                return this.$tc('sw-cms.general.disabledAddingBlocksToolTip');
            }

            return this.$tc('sw-cms.detail.sidebar.titleBlockOverview');
        },

        pageSections() {
            return this.page.sections;
        },

        sidebarItemSettings() {
            if (this.selectedBlock !== null) {
                return this.$tc('sw-cms.detail.sidebar.titleBlockSettings');
            }

            return this.$tc('sw-cms.detail.sidebar.titleSectionSettings');
        },

        tooltipDisabled() {
            return {
                message: this.$tc('sw-cms.detail.tooltip.cannotSelectProductPageLayout'),
                disabled: this.page.type !== 'product_detail',
            };
        },

        demoCriteria() {
            if (this.demoEntity === 'product') {
                const criteria = new Criteria();
                criteria.addAssociation('options.group');

                return criteria;
            }

            return new Criteria();
        },

        demoContext() {
            if (this.demoEntity === 'product') {
                return { ...Shopware.Context.api, inheritance: true };
            }

            return Shopware.Context.api;
        },

        blockTypes() {
            return Object.keys(this.cmsBlocks);
        },
    },

    methods: {
        onPageTypeChange() {
            this.$emit('page-type-change');
        },

        onDemoEntityChange(demoEntityId) {
            this.$emit('demo-entity-change', demoEntityId);
        },

        onCloseBlockConfig() {
            Shopware.State.commit('cmsPageState/removeSelectedBlock');
            Shopware.State.commit('cmsPageState/removeSelectedSection');
        },

        openSectionSettings(sectionIndex) {
            this.$refs.pageConfigSidebar.openContent();
            this.$nextTick(() => {
                this.$refs.sectionConfigSidebar[sectionIndex].collapseItem();
            });
        },

        blockIsRemovable(block) {
            return (this.cmsBlocks[block.type].removable !== false) && this.isSystemDefaultLanguage;
        },

        async onBlockDragSort(dragData, dropData, validDrop) {
            if (validDrop !== true) {
                return;
            }

            const dragSectionIndex = dragData.sectionIndex;
            const dropSectionIndex = dropData.sectionIndex;

            if (dragSectionIndex < 0 || dropSectionIndex < 0) {
                return;
            }

            if (dragData.block.sectionPosition !== dropData.block.sectionPosition) {
                dragData.block.isDragging = true;
                dragData.block.sectionPosition = dropData.block.sectionPosition;
            }

            // set current drag index to initial drag start index
            if (this.currentDragSectionIndex === null) {
                this.currentDragSectionIndex = dragSectionIndex;
            }

            // check if the section where the block is moved already has the block
            const dropSectionHasBlock = this.page.sections[dropSectionIndex].blocks.has(dragData.block.id);
            let isCrossSectionMove = false;
            if (this.currentDragSectionIndex !== dropSectionIndex && !dropSectionHasBlock) {
                dragData.block.isDragging = true;
                isCrossSectionMove = true;

                // calculate the remove index (this may differ since the block is moved each time it enters a new
                // section while the dragSectionIndex is the static start index of the drag
                let removeIndex = dragSectionIndex;
                if (this.currentDragSectionIndex !== dragSectionIndex &&
                    Math.abs(this.currentDragSectionIndex - dropSectionIndex) === 1) {
                    removeIndex = this.currentDragSectionIndex;
                }

                // drag direction is upwards so the currentDragSectionIndex is incremented
                if (this.currentDragSectionIndex - dropSectionIndex < 0) {
                    this.currentDragSectionIndex += 1;
                }

                // drag direction is downwards so the currentDragSectionIndex is decremented
                if (this.currentDragSectionIndex - dropSectionIndex > 0) {
                    this.currentDragSectionIndex -= 1;
                }

                dragData.block.sectionId = this.page.sections[dropSectionIndex].id;

                await this.blockRepository.save(dragData.block);

                // Add and remove the blocks from the sidebar for display reasons
                this.page.sections[dropSectionIndex].blocks.add(dragData.block);
                this.page.sections[removeIndex].blocks.remove(dragData.block.id);
            } else {
                // move item inside the section
                this.page.sections[dropSectionIndex].blocks.moveItem(dragData.block.position, dropData.block.position);
            }

            this.$emit('block-navigator-sort', isCrossSectionMove);
        },

        onSidebarNavigatorClick() {
            if (!this.$refs.blockNavigator.isActive) {
                return;
            }

            if (localStorage.getItem('cmsNavigatorDontRemind') === 'true') {
                this.onSidebarNavigationConfirm();
                return;
            }

            this.navigatorDontRemind = false;
            this.showSidebarNavigatorModal = true;
        },

        onSidebarNavigationConfirm() {
            if (this.navigatorDontRemind) {
                localStorage.setItem('cmsNavigatorDontRemind', true);
            }

            this.$emit('page-save');
            this.showSidebarNavigatorModal = false;
        },

        onSidebarNavigationCancel() {
            this.showSidebarNavigatorModal = false;
            this.$nextTick(() => {
                this.$refs.pageConfigSidebar.openContent();
            });
        },

        cloneBlock(block, sectionId) {
            const newBlock = this.blockRepository.create();

            const blockClone = cloneDeep(block);
            blockClone.position = block.position + 1;
            blockClone.sectionId = sectionId;
            blockClone.sectionPosition = block.sectionPosition;
            blockClone.slots = [];

            Object.assign(newBlock, blockClone);

            this.cloneSlotsInBlock(block, newBlock);

            return newBlock;
        },

        cloneSlotsInBlock(block, newBlock) {
            block.slots.forEach(slot => {
                const element = this.slotRepository.create();
                element.id = slot.id;
                element.blockId = newBlock.id;
                element.slot = slot.slot;
                element.type = slot.type;
                element.config = cloneDeep(slot.config);
                element.data = cloneDeep(slot.data);

                newBlock.slots.push(element);
            });
        },

        getDragData(block, sectionIndex) {
            return {
                delay: 300,
                dragGroup: 'cms-navigator',
                data: { block, sectionIndex },
                validDragCls: null,
                onDragEnter: this.onBlockDragSort,
                onDrop: this.onBlockDragStop,
            };
        },

        getDropData(block, sectionIndex) {
            return {
                dragGroup: 'cms-navigator',
                data: { block, sectionIndex },
                onDrop: this.onBlockDropAbort,
            };
        },

        onBlockDragStop(dragData) {
            this.currentDragSectionIndex = null;
            dragData.block.isDragging = false;
        },

        onBlockDropAbort(dragData, dropData) {
            const dragSectionIndex = dragData.sectionIndex;
            const dropSectionIndex = dropData.sectionIndex;
            if (dragSectionIndex < 0 || dropSectionIndex < 0) {
                return;
            }

            const dragSectionHasBlock = this.page.sections[dragSectionIndex].blocks.has(dragData.block.id);
            const dropSectionHasBlock = this.page.sections[dropSectionIndex].blocks.has(dragData.block.id);
            if (!dragSectionHasBlock && !dropSectionHasBlock) {
                this.page.sections[dragSectionIndex].blocks.add(dragData.block);
            }
        },

        onBlockStageDrop(dragData, dropData) {
            if (!dropData || !dragData.block || dropData.dropIndex < 0 || !dropData.section) {
                return;
            }

            const section = dropData.section;
            const blockConfig = this.cmsBlocks[dragData.block.name];
            const newBlock = this.blockRepository.create();

            newBlock.type = dragData.block.name;
            newBlock.position = dropData.dropIndex;
            newBlock.sectionPosition = dropData.sectionPosition;
            newBlock.sectionId = section.id;

            Object.assign(
                newBlock,
                cloneDeep(this.blockConfigDefaults),
                cloneDeep(blockConfig.defaultConfig || {}),
            );

            Object.keys(blockConfig.slots).forEach((slotName) => {
                const slotConfig = blockConfig.slots[slotName];
                const element = this.slotRepository.create();
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

            this.page.sections[section.position].blocks.splice(dropData.dropIndex, 0, newBlock);

            this.$emit('block-stage-drop');
            this.$emit('current-block-change', section.id, newBlock);
        },

        moveSectionUp(section) {
            this.page.sections.moveItem(section.position, section.position - 1);

            this.pageUpdate();
        },

        moveSectionDown(section) {
            this.page.sections.moveItem(section.position, section.position + 1);

            this.pageUpdate();
        },

        onSectionDuplicate(section) {
            this.$emit('section-duplicate', section);
        },

        onSectionDelete(sectionId) {
            Shopware.State.commit('cmsPageState/removeSelectedSection');
            this.page.sections.remove(sectionId);
            this.pageUpdate();
        },

        onBlockDelete(block, section) {
            if (!section) {
                section = this.page.sections.get(block.sectionId);
            }

            section.blocks.remove(block.id);

            if (this.selectedBlock && this.selectedBlock.id === block.id) {
                Shopware.State.commit('cmsPageState/removeSelectedBlock');
            }

            this.pageUpdate();
        },

        onBlockDuplicate(block, section) {
            if (!section) {
                section = this.page.sections.get(block.sectionId);
            }

            this.$emit('block-duplicate', block, section);
        },

        onRemoveSectionBackgroundMedia(section) {
            section.backgroundMediaId = null;
            section.backgroundMedia = null;

            this.pageUpdate();
        },

        onSetSectionBackgroundMedia([mediaItem], section) {
            section.backgroundMediaId = mediaItem.id;
            section.backgroundMedia = mediaItem;

            this.pageUpdate();
        },

        successfulUpload(media, section) {
            section.backgroundMediaId = media.targetId;

            this.mediaRepository.get(media.targetId).then((mediaItem) => {
                section.backgroundMedia = mediaItem;
                this.pageUpdate();
            });
        },

        uploadTag(section) {
            return `cms-section-media-config-${section.id}`;
        },

        getMainContentBlocks(sectionBlocks) {
            return sectionBlocks.filter((block) => this.blockTypeExists(block.type) && block.sectionPosition === 'main');
        },

        getSidebarContentBlocks(sectionBlocks) {
            return sectionBlocks.filter((block) => this.blockTypeExists(block.type) && block.sectionPosition === 'sidebar');
        },

        pageUpdate() {
            this.$emit('page-update');
        },

        onOpenLayoutAssignment() {
            this.$emit('open-layout-assignment');
        },

        blockTypeExists(type) {
            return this.blockTypes.includes(type);
        },
    },
});
