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
    name: 'sw-cms-el-config-image',
    load: () => import('./src/sw-cms-el-config-image'),
    public: {
        emits: [
            'element-update',
        ],
        blocks: [
            'sw_cms_element_image_config',
            'sw_cms_element_image_config_media_upload',
            'sw_cms_element_image_config_media_modal',
            'sw_cms_element_image_config_display_mode',
            'sw_cms_element_image_config_min_height',
            'sw_cms_element_image_config_vertical_align',
            'sw_cms_element_image_config_horizontal_align',
            'sw_cms_element_image_config_link',
        ],
        computed: [
            'mediaRepository',
            'uploadTag',
            'previewSource',
            'assetFilter',
            'displayModeOptions',
            'verticalAlignOptions',
            'horizontalAlignOptions',
        ],
        methods: [
            'createdComponent',
            'onImageUpload',
            'onImageRemove',
            'onCloseModal',
            'onSelectionChanges',
            'updateElementData',
            'onOpenMediaModal',
            'onChangeMinHeight',
            'formatMinHeight',
            'isUnitlessNumber',
            'onChangeDisplayMode',
            'onChangeIsDecorative',
            'emitUpdate',
        ],
    },
});
