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
    name: 'sw-cms-el-config-youtube-video',
    load: () => import('./src/sw-cms-el-config-youtube-video'),
    public: {
        emits: [
            'element-update',
        ],
        blocks: [
            'sw_cms_element_youtube_video_config',
            'sw_cms_element_youtube_video_config_video_id',
            'sw_cms_element_youtube_video_config_title',
            'sw_cms_element_youtube_video_config_autoplay',
            'sw_cms_element_youtube_video_config_loop',
            'sw_cms_element_youtube_video_config_show_controls',
            'sw_cms_element_youtube_video_config_advanced_privacy_mode',
            'sw_cms_element_youtube_video_config_start',
            'sw_cms_element_youtube_video_config_end',
            'sw_cms_element_youtube_video_config_display_mode',
            'sw_cms_element_youtube_video_config_needs_confirmation',
            'sw_cms_element_youtube_video_config_preview_media',
            'sw_cms_element_youtube_video_config_preview_media_display',
            'sw_cms_element_youtube_video_config_preview_media_upload_listener',
            'sw_cms_element_youtube_video_config_preview_media_modal',
        ],
        computed: [
            'startValue',
            'endValue',
            'videoID',
            'mediaRepository',
            'uploadTag',
            'previewSource',
            'displayModeOptions',
        ],
        methods: [
            'createdComponent',
            'setTimeValue',
            'convertTimeToInputFormat',
            'convertTimeToUrlFormat',
            'shortenLink',
            'onImageUpload',
            'onImageRemove',
            'onCloseModal',
            'onSelectionChanges',
            'updateElementData',
            'onOpenMediaModal',
        ],
    },
});
