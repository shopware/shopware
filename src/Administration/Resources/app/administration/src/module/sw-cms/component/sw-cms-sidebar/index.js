import template from './sw-cms-sidebar.html.twig';
import CMS from '../../constant/sw-cms.constant';
import './sw-cms-sidebar.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;
const types = Shopware.Utils.types;

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'acl',
        'cmsService',
        'repositoryFactory',
        'feature',
        'cmsBlockFavorites',
        'cmsPageTypeService',
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
        pageTypes() {
            return this.cmsPageTypeService.getTypes();
        },

        blockRepository() {
            return this.repositoryFactory.create('cms_block');
        },

        slotRepository() {
            return this.repositoryFactory.create('cms_slot');
        },

        cmsBlocks() {
            const currentPageType = Shopware.State.get('cmsPageState').currentPageType;

            const blocks = Object.entries(this.cmsService.getCmsBlockRegistry()).filter(([name, block]) => {
                return block.hidden !== true && this.cmsService.isBlockAllowedInPageType(name, currentPageType);
            });

            return Object.fromEntries(blocks);
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
                const criteria = new Criteria(1, 25);
                criteria.addAssociation('options.group');

                return criteria;
            }

            return new Criteria(1, 25);
        },

        demoContext() {
            if (this.demoEntity === 'product') {
                return { ...Shopware.Context.api, inheritance: true };
            }

            return Shopware.Context.api;
        },

        blockTypes() {
            return Object.keys(this.cmsService.getCmsBlockRegistry());
        },

        pageConfigErrors() {
            return [this.pageNameError].filter(error => !!error);
        },

        hasPageConfigErrors() {
            return this.pageConfigErrors.length > 0;
        },

        showDefaultLayoutSelection() {
            if (!this.acl.can('system_config.editor')) {
                return false;
            }

            if (this.page.type === 'product_list') {
                return true;
            }

            if (this.page.type === 'product_detail' && this.feature.isActive('v6.6.0.0')) {
                return true;
            }

            return false;
        },

        cmsBlocksBySelectedBlockCategory() {
            const result = Object.values(this.cmsBlocks).filter(b => b.hidden !== true);

            if (this.currentBlockCategory === 'favorite') {
                return result.filter(b => this.cmsBlockFavorites.isFavorite(b.name));
            }

            return result.filter(b => b.category === this.currentBlockCategory);
        },

        ...mapPropertyErrors('page', ['name']),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.blockTypes.some(blockName => this.cmsBlockFavorites.isFavorite(blockName))) {
                this.currentBlockCategory = 'favorite';
            }
        },

        onPageTypeChange(pageType) {
            this.$emit('page-type-change', pageType);
        },

        onDemoEntityChange(demoEntityId) {
            this.$emit('demo-entity-change', demoEntityId);
        },

        onCloseBlockConfig() {
            Shopware.State.commit('cmsPageState/removeSelectedBlock');
            Shopware.State.commit('cmsPageState/removeSelectedSection');
        },

        isDisabledPageType(pageType) {
            if (this.page.type === 'product_detail') {
                return true;
            }

            if (this.page.type.includes('custom_entity_')) {
                return !pageType.name.includes('custom_entity_');
            }

            return pageType.name === 'product_detail' || pageType.name.includes('custom_entity_');
        },

        openSectionSettings(sectionIndex) {
            this.$refs.pageConfigSidebar.openContent();
            this.$nextTick(() => {
                this.$refs.sectionConfigSidebar[sectionIndex].collapseItem();
            });
        },

        blockIsRemovable(block) {
            const cmsBlocks = this.cmsService.getCmsBlockRegistry();
            return (cmsBlocks[block.type].removable !== false) && this.isSystemDefaultLanguage;
        },

        blockIsUnique(block) {
            if (this.page.type !== CMS.PAGE_TYPES.PRODUCT_DETAIL) {
                return false;
            }

            return block.slots.some((slot) => {
                return CMS.UNIQUE_SLOTS_KEBAB.includes(slot.type);
            });
        },

        blockIsDuplicable(block) {
            return !this.blockIsUnique(block);
        },

        sectionIsDuplicable(section) {
            return section.blocks.every((block) => this.blockIsDuplicable(block));
        },

        async onBlockDragSort(dragData, dropData, validDrop) {
            if (validDrop !== true) {
                return;
            }

            const dragSectionIndex = dragData.sectionIndex;
            const dropSectionIndex = dropData.sectionIndex;

            const dropSection = this.page.sections[dropSectionIndex];

            if (dragSectionIndex < 0 || dragSectionIndex >= this.page.sections.length ||
                dropSectionIndex < 0 || dropSectionIndex >= this.page.sections.length) {
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
            const dropSectionHasBlock = dropSection.blocks.has(dragData.block.id);
            if (this.currentDragSectionIndex !== dropSectionIndex && !dropSectionHasBlock) {
                dragData.block.isDragging = true;

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

                dragData.block.sectionId = dropSection.id;

                dropSection.blocks.add(dragData.block);

                const oldSection = this.page.sections[removeIndex];
                oldSection.blocks.remove(dragData.block.id);
                oldSection._origin.blocks.remove(dragData.block.id);

                this.refreshPosition(oldSection.blocks);
                this.refreshPosition(dropSection.blocks);
                return;
            }

            if (dragData.block.position === dropData.block.position) {
                return;
            }

            // move item inside the section
            this.page.sections[dropSectionIndex].blocks.moveItem(dragData.block.position, dropData.block.position);
            this.refreshPosition(dropSection.blocks);
        },

        refreshPosition(elements) {
            return elements.forEach((element, index) => {
                element.position = index;
            });
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

            const cmsBlocks = this.cmsService.getCmsBlockRegistry();
            const section = dropData.section;
            const blockConfig = cmsBlocks[dragData.block.name];
            const newBlock = this.blockRepository.create();

            newBlock.type = dragData.block.name;
            newBlock.position = dropData.dropIndex;
            newBlock.sectionPosition = dropData.sectionPosition;
            newBlock.sectionId = section.id;

            newBlock.visibility = {
                desktop: true,
                tablet: true,
                mobile: true,
            };

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

                const slotDefaultData = slotConfig.default?.data;
                if ([slotDefaultData?.media?.source, slotDefaultData?.sliderItems?.source].includes('default')) {
                    element.config = { ...element.config, ...slotDefaultData };
                }

                newBlock.slots.add(element);
            });
            this.page.sections[section.position].blocks.splice(dropData.dropIndex, 0, newBlock);

            this.$emit('block-stage-drop');
            this.$emit('current-block-change', section.id, newBlock);
        },

        moveSectionUp(section) {
            this.page.sections.moveItem(section.position, section.position - 1);

            this.$emit('page-save', true);
        },

        moveSectionDown(section) {
            this.page.sections.moveItem(section.position, section.position + 1);

            this.$emit('page-save', true);
        },

        onSectionDuplicate(section) {
            this.$emit('section-duplicate', section);
        },

        onSectionDelete(sectionId) {
            Shopware.State.commit('cmsPageState/removeSelectedSection');
            this.page.sections.remove(sectionId);
            this.$emit('page-save');
        },

        onBlockDelete(block, section) {
            if (!section) {
                section = this.page.sections.get(block.sectionId);
            }

            section.blocks.remove(block.id);

            if (this.selectedBlock && this.selectedBlock.id === block.id) {
                Shopware.State.commit('cmsPageState/removeSelectedBlock');
            }

            this.$emit('page-save', true);
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

        onToggleBlockFavorite(blockName) {
            this.cmsBlockFavorites.update(!this.cmsBlockFavorites.isFavorite(blockName), blockName);
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

        onOpenLayoutSetAsDefault() {
            this.$emit('open-layout-set-as-default');
        },

        blockTypeExists(type) {
            return this.blockTypes.includes(type);
        },

        onVisibilityChange(selectedBlock, viewport, isVisible) {
            selectedBlock.visibility[viewport] = isVisible;
        },
    },
};
