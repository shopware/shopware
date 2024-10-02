import { type PropType } from 'vue';
import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import template from './sw-cms-sidebar.html.twig';
import CMS from '../../constant/sw-cms.constant';
import './sw-cms-sidebar.scss';
import { type PageType } from '../../service/cms-page-type.service';
import type MediaUploadResult from '../../shared/MediaUploadResult';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;
const types = Shopware.Utils.types;

type DraggableBlock = EntitySchema.Entity<'cms_block'> & {
    isDragging?: boolean;
};

type DragData = {
    block: DraggableBlock;
    sectionIndex: number;
};

type DropData = {
    dropIndex: number;
    block: DraggableBlock;
    section: EntitySchema.Entity<'cms_section'> | null;
    sectionPosition: string;
    sectionIndex: number;
};

type DragObject = {
    delay: number;
    dragGroup: string;
    validDragCls: null;
    data: DragData;
    onDragEnter: (dragData: DragData, dropData: DropData, validDrop: boolean) => void;
    onDrop: (dragData: DragData) => void;
};

type DropObject = {
    dragGroup: string;
    data: DropData;
    onDrop: (dragData: DragData, dropData: DropData) => void;
};

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'acl',
        'cmsService',
        'repositoryFactory',
        'feature',
        'cmsBlockFavorites',
        'cmsPageTypeService',
    ],

    emits: [
        'page-type-change', 'demo-entity-change', 'page-save', 'block-stage-drop', 'current-block-change',
        'section-duplicate', 'block-duplicate', 'page-update', 'open-layout-assignment', 'open-layout-set-as-default',
    ],

    mixins: [
        Mixin.getByName('cms-state'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        page: {
            type: Object as PropType<EntitySchema.Entity<'cms_page'>>,
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

        isDefaultLayout: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            demoEntityId: this.demoEntityIdProp,
            currentBlockCategory: 'text' as string,
            currentDragSectionIndex: null as number | null,
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
            const currentPageType = Shopware.Store.get('cmsPage').currentPageType;

            if (!currentPageType) {
                return {};
            }

            const blocks = Object.entries(this.cmsService.getCmsBlockRegistry()).filter(([name, block]) => {
                return block && !block.hidden && this.cmsService.isBlockAllowedInPageType(name, currentPageType);
            });

            return Object.fromEntries(blocks);
        },

        cmsBlockCategories() {
            const defaultCategories = [
                {
                    value: 'favorite',
                    label: 'sw-cms.detail.label.blockCategoryFavorite',
                },
                {
                    value: 'text',
                    label: 'sw-cms.detail.label.blockCategoryText',
                },
                {
                    value: 'image',
                    label: 'sw-cms.detail.label.blockCategoryImage',
                },
                {
                    value: 'video',
                    label: 'sw-cms.detail.label.blockCategoryVideo',
                },
                {
                    value: 'text-image',
                    label: 'sw-cms.detail.label.blockCategoryTextImage',
                },
                {
                    value: 'commerce',
                    label: 'sw-cms.detail.label.blockCategoryCommerce',
                },
                {
                    value: 'sidebar',
                    label: 'sw-cms.detail.label.blockCategorySidebar',
                },
                {
                    value: 'form',
                    label: 'sw-cms.detail.label.blockCategoryForm',
                },
                {
                    value: 'html',
                    label: 'sw-cms.detail.label.blockCategoryHtml',
                },
            ];

            // Check if blocks with the category 'app' are available
            if (Object.values(this.cmsService.getCmsBlockRegistry()).some(block => {
                return block?.category === 'app';
            })) {
                defaultCategories.push({
                    value: 'app',
                    label: 'sw-cms.detail.label.blockCategoryApp',
                });
            }

            // Get all missing categories from the block registry
            const categories = Object.values(this.cmsService.getCmsBlockRegistry()).map(block => block?.category);
            const uniqueCategories = [...new Set(categories)] as string[];

            // Add all missing categories to the default categories
            uniqueCategories.forEach(category => {
                if (defaultCategories.some(defaultCategory => defaultCategory.value === category)) {
                    return;
                }

                if (this.isDuplicateCategory(category)) {
                    return;
                }

                defaultCategories.push({
                    value: category,
                    label: `apps.sw-cms.detail.label.blockCategory.${category}`,
                });
            });

            return defaultCategories;
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
            if (
                !this.acl.can('system_config:read')
                || !this.acl.can('system_config:update')
                || !this.acl.can('system_config:create')
                || !this.acl.can('system_config:delete')
            ) {
                return false;
            }

            if (this.page.type === 'product_list' || this.page.type === 'product_detail') {
                return !this.isDefaultLayout;
            }

            return false;
        },

        cmsBlocksBySelectedBlockCategory() {
            const result = Object.values(this.cmsBlocks).filter(block => block && !block.hidden);

            if (this.currentBlockCategory === 'favorite') {
                return result.filter(block => block && this.cmsBlockFavorites.isFavorite(block.name));
            }

            return result.filter(block => block && block.category === this.currentBlockCategory);
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

        onPageTypeChange(pageType: PageType) {
            this.$emit('page-type-change', pageType);
        },

        onDemoEntityChange(demoEntityId: string) {
            this.$emit('demo-entity-change', demoEntityId);
        },

        onCloseBlockConfig() {
            const store = Shopware.Store.get('cmsPage');
            store.removeSelectedBlock();
            store.removeSelectedSection();
        },

        isDisabledPageType(pageType: PageType) {
            if (this.page.type === 'product_detail') {
                return true;
            }

            if (this.page.type.includes('custom_entity_')) {
                return !pageType.name.includes('custom_entity_');
            }

            return pageType.name === 'product_detail' || pageType.name.includes('custom_entity_');
        },

        openSectionSettings(sectionIndex: number) {
            Shopware.Store.get('cmsPage').setSection(this.page.sections![sectionIndex]);

            const itemConfigSidebar = this.$refs.itemConfigSidebar as { openContent: () => void };
            itemConfigSidebar.openContent();
        },

        blockIsRemovable(block: EntitySchema.Entity<'cms_block'>) {
            const cmsBlocks = this.cmsService.getCmsBlockRegistry();
            return cmsBlocks[block.type]?.removable && this.isSystemDefaultLanguage;
        },

        blockIsUnique(block: EntitySchema.Entity<'cms_block'>) {
            if (this.page.type !== CMS.PAGE_TYPES.PRODUCT_DETAIL) {
                return false;
            }

            return block.slots!.some((slot) => {
                return CMS.UNIQUE_SLOTS_KEBAB.includes(slot.type);
            });
        },

        blockIsDuplicable(block: EntitySchema.Entity<'cms_block'>) {
            return !this.blockIsUnique(block);
        },

        sectionIsDuplicable(section: EntitySchema.Entity<'cms_section'>) {
            return section.blocks!.every((block) => this.blockIsDuplicable(block));
        },

        onBlockDragSort(dragData: DragData, dropData: DropData, validDrop: boolean) {
            if (!validDrop) {
                return;
            }

            const dragSectionIndex = dragData.sectionIndex;
            const dropSectionIndex = dropData.sectionIndex;

            const dropSection = this.page.sections![dropSectionIndex];

            if (dragSectionIndex < 0 || dragSectionIndex >= this.page.sections!.length ||
                dropSectionIndex < 0 || dropSectionIndex >= this.page.sections!.length) {
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
            const dropSectionHasBlock = dropSection.blocks!.has(dragData.block.id);
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

                dropSection.blocks!.add(dragData.block);

                const oldSection = this.page.sections![removeIndex];

                oldSection.blocks!.remove(dragData.block.id);
                oldSection._origin.blocks!.remove(dragData.block.id);

                this.refreshPosition(oldSection.blocks!);
                this.refreshPosition(dropSection.blocks!);
                return;
            }

            if (dragData.block.position === dropData.block.position) {
                return;
            }

            // move item inside the section
            this.page.sections![dropSectionIndex].blocks!.moveItem(dragData.block.position, dropData.block.position);
            this.refreshPosition(dropSection.blocks!);
        },

        refreshPosition(blocks: EntityCollection<'cms_block'>) {
            return blocks.forEach((block, index) => {
                block.position = index;
            });
        },

        onSidebarNavigatorClick() {
            const blockNavigator = this.$refs.blockNavigator as { isActive: boolean };

            if (!blockNavigator.isActive) {
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
                localStorage.setItem('cmsNavigatorDontRemind', 'true');
            }

            this.$emit('page-save');
            this.showSidebarNavigatorModal = false;
        },

        onSidebarNavigationCancel() {
            const pageConfigSidebar = this.$refs.pageConfigSidebar as { openContent: () => void };

            this.showSidebarNavigatorModal = false;
            void this.$nextTick(() => {
                pageConfigSidebar.openContent();
            });
        },

        getDragData(block: EntitySchema.Entity<'cms_block'>, sectionIndex: number): DragObject {
            return {
                delay: 300,
                dragGroup: 'cms-navigator',
                data: { block, sectionIndex },
                validDragCls: null,
                onDragEnter: this.onBlockDragSort.bind(this),
                onDrop: this.onBlockDragStop.bind(this),
            };
        },

        getDropData(block: EntitySchema.Entity<'cms_block'>, sectionIndex: number): DropObject {
            return {
                dragGroup: 'cms-navigator',
                data: {
                    block,
                    sectionIndex,
                    dropIndex: -1,
                    section: null,
                    sectionPosition: '',
                },
                onDrop: this.onBlockDropAbort.bind(this),
            };
        },

        onBlockDragStop(data: DragData) {
            this.currentDragSectionIndex = null;
            data.block.isDragging = false;
        },

        onBlockDropAbort(dragData: DragData, dropData: DropData) {
            const dragSectionIndex = dragData.sectionIndex;
            const dropSectionIndex = dropData.sectionIndex;

            if (dragSectionIndex < 0 || dropSectionIndex < 0) {
                return;
            }

            const dragSectionHasBlock = this.page.sections![dragSectionIndex].blocks!.has(dragData.block.id);
            const dropSectionHasBlock = this.page.sections![dropSectionIndex].blocks!.has(dragData.block.id);

            if (!dragSectionHasBlock && !dropSectionHasBlock) {
                this.page.sections![dragSectionIndex].blocks!.add(dragData.block);
            }
        },

        onBlockStageDrop(dragData: DragData, dropData: DropData) {
            if (!dropData || !dragData.block || dropData.dropIndex < 0 || !dropData.section) {
                return;
            }

            const cmsBlocks = this.cmsService.getCmsBlockRegistry();
            const section = dropData.section;
            const blockConfig = cmsBlocks[dragData.block.name!];
            const newBlock = this.blockRepository.create();

            newBlock.type = dragData.block.name!;
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
                cloneDeep(blockConfig?.defaultConfig || {}),
            );

            Object.keys(blockConfig?.slots as object).forEach((slotName) => {
                const slotConfig = blockConfig?.slots![slotName];
                const element = this.slotRepository.create();
                element.blockId = newBlock.id;
                element.slot = slotName;

                if (typeof slotConfig === 'object') {
                    element.type = slotConfig.type;

                    if (slotConfig.default && types.isPlainObject(slotConfig.default)) {
                        Object.assign(element, cloneDeep(slotConfig.default));
                    }

                    const slotDefaultData = slotConfig.default?.data;

                    if ([slotDefaultData?.media?.source, slotDefaultData?.sliderItems?.source].includes('default')) {
                        element.config = { ...element.config as object, ...slotDefaultData };
                    }
                } else {
                    element.type = slotConfig as unknown as string;
                }

                newBlock.slots!.add(element);
            });
            this.page.sections![section.position].blocks!.splice(dropData.dropIndex, 0, newBlock);

            this.$emit('block-stage-drop');
            this.$emit('current-block-change', section.id, newBlock);
        },

        moveSectionUp(section: EntitySchema.Entity<'cms_section'>) {
            this.page.sections!.moveItem(section.position, section.position - 1);

            this.$emit('page-save', true);
        },

        moveSectionDown(section: EntitySchema.Entity<'cms_section'>) {
            this.page.sections!.moveItem(section.position, section.position + 1);

            this.$emit('page-save', true);
        },

        onSectionDuplicate(section: EntitySchema.Entity<'cms_section'>) {
            this.$emit('section-duplicate', section);
        },

        onSectionDelete(sectionId: string) {
            Shopware.Store.get('cmsPage').removeSelectedSection();
            this.page.sections!.remove(sectionId);
            this.$emit('page-save');
        },

        onBlockDelete(block: EntitySchema.Entity<'cms_block'>, section: EntitySchema.Entity<'cms_section'> | null) {
            if (!section) {
                section = this.page.sections!.get(block.sectionId);
            }

            section?.blocks?.remove(block.id);

            if (this.selectedBlock && this.selectedBlock.id === block.id) {
                Shopware.Store.get('cmsPage').removeSelectedBlock();
            }

            this.$emit('page-save', true);
        },

        onBlockDuplicate(block: EntitySchema.Entity<'cms_block'>, section: EntitySchema.Entity<'cms_section'> | null) {
            if (!section) {
                section = this.page.sections!.get(block.sectionId);
            }

            this.$emit('block-duplicate', block, section);
        },

        onRemoveSectionBackgroundMedia(section: EntitySchema.Entity<'cms_section'>) {
            section.backgroundMediaId = undefined;
            section.backgroundMedia = undefined;

            this.pageUpdate();
        },

        onSetSectionBackgroundMedia(
            [mediaItem]: [EntitySchema.Entity<'media'>],
            section: EntitySchema.Entity<'cms_section'>,
        ) {
            section.backgroundMediaId = mediaItem.id;
            section.backgroundMedia = mediaItem;

            this.pageUpdate();
        },

        onToggleBlockFavorite(blockName: string) {
            this.cmsBlockFavorites.update(!this.cmsBlockFavorites.isFavorite(blockName), blockName);
        },

        successfulUpload(media: MediaUploadResult, section: EntitySchema.Entity<'cms_section'>) {
            section.backgroundMediaId = media.targetId;

            void this.mediaRepository.get(media.targetId).then((mediaItem) => {
                section.backgroundMedia = mediaItem ?? undefined;
                this.pageUpdate();
            });
        },

        uploadTag(section: EntitySchema.Entity<'cms_section'>) {
            return `cms-section-media-config-${section.id}`;
        },

        getMainContentBlocks(sectionBlocks: EntityCollection<'cms_block'>) {
            return sectionBlocks.filter((block) => this.blockTypeExists(block.type) && block.sectionPosition === 'main');
        },

        getSidebarContentBlocks(sectionBlocks: EntityCollection<'cms_block'>) {
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

        blockTypeExists(type: string) {
            return this.blockTypes.includes(type);
        },

        onVisibilityChange(selectedBlock: EntitySchema.Entity<'cms_block'>, viewport: string, isVisible: boolean) {
            (selectedBlock.visibility as { [key: string]: boolean })[viewport] = isVisible;
        },

        /**
         * @deprecated tag:v6.7.0 - Remove the duplicate category check and all usages.
         * Use the auto-generated category label instead of the hardcoded option
         * value inside the template.
         */
        isDuplicateCategory(categoryValue: string) {
            /**
             * This method is a unusual hack to prevent the category from being added twice.
             * Recommended for plugin developer is to remove the hardcoded option value
             * inside the template and use the auto-generated category label instead.
             * */
            const swCmsSidebarTemplate = Shopware.Template.getRenderedTemplate('sw-cms-sidebar');
            return swCmsSidebarTemplate?.includes(`value="${categoryValue}"`);
        },
    },
});
