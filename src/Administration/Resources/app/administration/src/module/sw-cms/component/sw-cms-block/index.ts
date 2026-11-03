import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-cms-block',
    load: () => import('./src/sw-cms-block'),
    public: {
        props: [
            'block',
            'active',
            'disabled',
            'hasWarnings',
            'hasErrors',
        ],
        emits: [
            'block-overlay-click',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_cms_block',
            'sw_cms_block_content',
            'sw_cms_block_slot_default',
            'sw_cms_block_overlay',
        ],
        computed: [
            'customBlockClass',
            'blockStyles',
            'blockPadding',
            'overlayClasses',
            'toolbarClasses',
            'assetFilter',
            'isVisible',
            'toggleButtonText',
            'expandedClass',
        ],
        methods: [
            'createdComponent',
            'onBlockOverlayClick',
            'toggleVisibility',
        ],
    },
});
