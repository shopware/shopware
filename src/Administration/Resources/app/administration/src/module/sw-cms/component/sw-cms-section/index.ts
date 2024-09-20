import { type PropType } from 'vue';
import template from './sw-cms-section.html.twig';
import './sw-cms-section.scss';
import type CmsVisibility from '../../shared/CmsVisibility';

const { Component, Mixin, Filter } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

type SlotsErrorObject = {
    parameters?: {
        elements: Array<{
            blockIds: string[]
        }>;
    };
};

type SlotConfigErrorObject = {
    parameters?: {
        elements: Array<{
            blockId: string
        }>;
    };
};

/**
 * @private
 * @package buyers-experience
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    inject: [
        'cmsService',
        'repositoryFactory',
    ],

    provide() {
        return {
            swCmsSectionEmitPageConfigOpen: this.emitPageConfigOpen.bind(this),
        };
    },

    emits: ['page-config-open', 'block-duplicate'],

    mixins: [
        Mixin.getByName('cms-state'),
    ],

    props: {
        page: {
            type: Object as PropType<EntitySchema.Entity<'cms_page'>>,
            required: true,
        },

        section: {
            type: Object as PropType<EntitySchema.Entity<'cms_section'>>,
            required: true,
        },

        active: {
            type: Boolean,
            required: false,
            default: false,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            isCollapsed: true,
            pageSlotconfigError: null as SlotConfigErrorObject | null,
        };
    },

    computed: {
        blockRepository() {
            return this.repositoryFactory.create('cms_block');
        },

        slotRepository() {
            return this.repositoryFactory.create('cms_slot');
        },

        sectionClasses() {
            return {
                'is--active': this.active,
                'is--boxed': this.section.sizingMode === 'boxed',
            };
        },

        sectionTypeClass() {
            return `is--${this.section.type}`;
        },

        customSectionClass() {
            return this.section.cssClass;
        },

        sectionStyles() {
            let backgroundMedia = null;

            if (this.section.backgroundMedia) {
                const url = this.section.backgroundMedia.url as string;

                if (this.section.backgroundMedia.id) {
                    backgroundMedia = `url("${url}")`;
                } else {
                    backgroundMedia = `url('${this.assetFilter(url)}')`;
                }
            }

            return {
                'background-color': this.section.backgroundColor || 'transparent',
                'background-image': backgroundMedia,
                'background-size': this.section.backgroundMediaMode,
            };
        },

        sectionSidebarClasses() {
            return {
                'is--empty': this.sideBarEmpty,
                'is--hidden': this.sectionMobileAndHidden,
                'is--expanded': this.expandedClass,
            };
        },

        sectionMobileAndHidden() {
            const view = Shopware.Store.get('cmsPageState').currentCmsDeviceView;
            return view === 'mobile' && this.section.mobileBehavior === 'hidden';
        },

        isSideBarType() {
            return this.section.type === 'sidebar';
        },

        sideBarEmpty() {
            return this.sideBarBlocks.length === 0;
        },

        blockCount() {
            return this.section.blocks!.length;
        },

        mainContentEmpty() {
            return this.mainContentBlocks.length === 0;
        },

        sideBarBlocks() {
            const sideBarBlocks = this.section.blocks!.filter((block => this.blockTypeExists(block.type)
                && block.sectionPosition === 'sidebar'));
            return sideBarBlocks.sort((a, b) => a.position - b.position);
        },

        mainContentBlocks() {
            const mainContentBlocks = this.section.blocks!.filter((block => this.blockTypeExists(block.type)
                && block.sectionPosition !== 'sidebar'));
            return mainContentBlocks.sort((a, b) => a.position - b.position);
        },

        assetFilter() {
            return Filter.getByName('asset');
        },

        blockTypes() {
            return Object.keys(this.cmsService.getCmsBlockRegistry());
        },

        isVisible() {
            const view = Shopware.Store.get('cmsPageState').currentCmsDeviceView;

            const visibility = this.section.visibility as CmsVisibility;

            return (view === 'desktop' && !visibility.desktop) ||
                (view === 'tablet-landscape' && !visibility.tablet) ||
                (view === 'mobile' && !visibility.mobile);
        },

        toggleButtonText() {
            return this.$tc('sw-cms.sidebar.contentMenu.visibilitySectionTextButton', this.isCollapsed ? 0 : 1);
        },

        expandedClass() {
            return {
                'is--expanded': this.isVisible && !this.isCollapsed,
            };
        },

        sectionContentClasses() {
            return {
                'is--empty': this.mainContentEmpty,
                'is--expanded': this.isVisible && !this.isCollapsed,
            };
        },

        ...mapPropertyErrors('page', [
            'slots',
            'slotConfig',
        ]),
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.section.backgroundMediaMode) {
                this.section.backgroundMediaMode = 'cover';
            }
        },

        openBlockBar() {
            if (this.disabled) {
                return;
            }

            this.$emit('page-config-open', 'blocks');
        },

        emitPageConfigOpen(arg: string) {
            this.$emit('page-config-open', arg);
        },

        onAddSectionBlock() {
            this.openBlockBar();
        },

        onBlockSelection(block: EntitySchema.Entity<'cms_block'>) {
            Shopware.Store.get('cmsPageState').setBlock(block);
            this.$emit('page-config-open', 'itemConfig');
        },

        onBlockDuplicate(block: EntitySchema.Entity<'cms_block'>, section: EntitySchema.Entity<'cms_section'>) {
            this.$emit('block-duplicate', block, section);
        },

        onBlockDelete(blockId: string) {
            this.section.blocks!.remove(blockId);

            if (this.selectedBlock && this.selectedBlock.id === blockId) {
                Shopware.Store.get('cmsPageState').removeSelectedBlock();
            }

            this.updateBlockPositions();
        },

        updateBlockPositions() {
            this.section.blocks!.forEach((block, index) => {
                block.position = index;
            });
        },

        getDropData(index: number, sectionPosition = 'main') {
            return { dropIndex: index, section: this.section, sectionPosition };
        },

        blockTypeExists(type: string) {
            return this.blockTypes.includes(type);
        },

        hasBlockErrors(block: EntitySchema.Entity<'cms_block'>) {
            return [
                this.hasUniqueBlockErrors(block),
                this.hasSlotConfigErrors(block),
            ].some((error) => error);
        },

        hasUniqueBlockErrors(block: EntitySchema.Entity<'cms_block'>) {
            const errorElements = (this.pageSlotsError as SlotsErrorObject)?.parameters?.elements;

            if (!errorElements) {
                return false;
            }

            return errorElements.some((errorType) => errorType.blockIds.includes(block.id));
        },

        hasSlotConfigErrors(block: EntitySchema.Entity<'cms_block'>) {
            const errorElements = (this.pageSlotconfigError as SlotConfigErrorObject)?.parameters?.elements;

            if (!errorElements) {
                return false;
            }

            return errorElements.some((missingConfig) => missingConfig.blockId === block.id);
        },

        toggleVisibility() {
            this.isCollapsed = !this.isCollapsed;
        },
    },
});
