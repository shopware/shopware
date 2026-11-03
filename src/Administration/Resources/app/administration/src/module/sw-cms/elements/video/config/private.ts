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
    name: 'sw-cms-el-config-video',
    load: () => import('./src/sw-cms-el-config-video'),
    public: {
        emits: [
            'element-update',
        ],
        blocks: [
            'sw_cms_element_video_config',
            'sw_cms_element_video_config_media_upload',
            'sw_cms_element_video_config_media_modal',
            'sw_cms_element_video_config_aria_label',
            'sw_cms_element_video_config_display_mode',
            'sw_cms_element_video_config_min_height',
            'sw_cms_element_video_config_vertical_align',
            'sw_cms_element_video_config_horizontal_align',
        ],
        computed: [
            'mediaRepository',
            'uploadTag',
            'previewSource',
            'displayModeOptions',
            'verticalAlignOptions',
            'horizontalAlignOptions',
        ],
        methods: [
            'createdComponent',
            'onVideoUpload',
            'onVideoRemove',
            'onCloseModal',
            'onSelectionChanges',
            'updateElementData',
            'onOpenMediaModal',
            'onChangeMinHeight',
            'emitUpdate',
        ],
    },
});
