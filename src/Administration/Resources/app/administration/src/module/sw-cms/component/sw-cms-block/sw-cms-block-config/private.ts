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
    name: 'sw-cms-block-config',
    load: () => import('./src/sw-cms-block-config'),
    public: {
        props: [
            'block',
        ],
        emits: [
            'block-delete',
            'block-duplicate',
        ],
        blocks: [
            'sw_cms_block_config',
            'sw_cms_block_config__quickactions',
            'sw_cms_block_config__quickaction_duplicate',
            'sw_cms_block_config__quickaction_delete',
            'sw_cms_block_config__settings',
            'sw_cms_block_config_name_field',
            'sw_cms_block_config_background_color_field',
            'sw_cms_block_config_background_image_field',
            'sw_cms_block_config_background_image_position_field',
        ],
        computed: [
            'uploadTag',
            'mediaRepository',
            'cmsPageState',
            'cmsBlocks',
            'blockConfig',
            'quickactionsDisabled',
            'duplicateDisabled',
            'combinedDuplicateDisabled',
            'combinedDuplicateClasses',
            'quickactionClasses',
            'backgroundModeOptions',
        ],
        methods: [
            'onSetBackgroundMedia',
            'successfulUpload',
            'removeMedia',
            'onBlockDelete',
            'onBlockDuplicate',
            'onBlockNameChange',
        ],
    },
});
