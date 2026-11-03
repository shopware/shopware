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
    name: 'sw-media-preview-v2',
    load: () => import('./src/sw-media-preview-v2'),
    public: {
        props: [
            'source',
            'showControls',
            'autoplay',
            'transparency',
            'useThumbnails',
            'hideTooltip',
            'mediaIsPrivate',
        ],
        emits: [
            'click',
            'media-preview-play',
        ],
        blocks: [
            'sw_media_preview_v2',
            'sw_media_preview_v2_no_media',
            'sw_media_preview_v2_no_media_icon',
            'sw_media_preview_v2_file_types',
            'sw_media_preview_v2_file_type_check',
            'sw_media_preview_v2_file_type_image',
            'sw_media_preview_v2_file_type_video',
            'sw_media_preview_v2_file_type_audio',
            'sw_media_preview_v2_file_type_placeholder',
            'sw_media_preview_v2_unsupported_format_warning',
        ],
        computed: [
            'mediaRepository',
            'mediaPreviewClasses',
            'transparencyClass',
            'canBeTransparent',
            'mimeType',
            'mimeTypeGroup',
            'isPlayable',
            'showUnsupportedFormatWarning',
            'isIcon',
            'placeholderIcon',
            'placeholderIconPath',
            'lockIsVisible',
            'previewUrl',
            'isUrl',
            'isFile',
            'isRelativePath',
            'alt',
            'mediaName',
            'mediaNameFilter',
            'assetFilter',
            'sourceSet',
            'videoCoverMedia',
            'videoCoverPoster',
            'hasVideoCover',
            'videoPreloadValue',
        ],
        methods: [
            'createdComponent',
            'beforeUnmountedComponent',
            'mountedComponent',
            'fetchSourceIfNecessary',
            'onPlayClick',
            'getDataUrlFromFile',
            'reloadMediaElement',
            'removeUrlPreview',
            'showEvent',
            'onMediaLibraryItemUpdated',
            'getCurrentMediaId',
            'ensureVideoCoverMedia',
            'getVideoCoverMediaId',
            'buildSourceSet',
        ],
    },
});
