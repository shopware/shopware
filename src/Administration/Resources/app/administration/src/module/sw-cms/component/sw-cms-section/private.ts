import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-cms-section',
    load: () => import('./src/sw-cms-section'),
    public: {
        props: [
            'page',
            'section',
            'active',
            'disabled',
        ],
        emits: [
            'page-config-open',
            'block-duplicate',
        ],
        blocks: [
            'sw_cms_section',
            'sw_cms_section_actions',
            'sw_cms_section_sidebar',
            'sw_cms_section_sidebar_block_empty',
            'sw_cms_section_sidebar_add_first_block',
            'sw_cms_section_sidebar_block',
            'sw_cms_section_sidebar_block_component',
            'sw_cms_section_content_block_slot',
            'sw_cms_section_add_sidebar_block',
            'sw_cms_section_content',
            'sw_cms_section_content_block_empty',
            'sw_cms_section_content_add_first_block',
            'sw_cms_section_content_block',
            'sw_cms_section_content_block_component',
            'sw_cms_section_content_block_component_slot',
            'sw_cms_section_add_content_block',
        ],
        computed: [
            'blockRepository',
            'slotRepository',
            'sectionClasses',
            'sectionTypeClass',
            'customSectionClass',
            'sectionStyles',
            'sectionSidebarClasses',
            'sectionMobileAndHidden',
            'isSideBarType',
            'sideBarEmpty',
            'blockCount',
            'mainContentEmpty',
            'sideBarBlocks',
            'mainContentBlocks',
            'assetFilter',
            'blockTypes',
            'isVisible',
            'toggleButtonText',
            'expandedClass',
            'sectionContentClasses',
        ],
        methods: [
            'createdComponent',
            'openBlockBar',
            'emitPageConfigOpen',
            'onAddSectionBlock',
            'onBlockSelection',
            'onBlockDuplicate',
            'onBlockDelete',
            'updateBlockPositions',
            'getDropData',
            'blockTypeExists',
            'hasBlockErrors',
            'hasUniqueBlockErrors',
            'hasSlotConfigErrors',
            'toggleVisibility',
            'getBlockComponent',
        ],
    },
});
