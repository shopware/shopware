import { isPlayableMediaFormat, shouldShowUnsupportedFormatWarning } from 'src/app/service/media-format.service';
import template from './sw-media-preview-v2.html.twig';
import './sw-media-preview-v2.scss';

const { Context, Filter } = Shopware;
const { fileReader, EventBus } = Shopware.Utils;

/**
 * @status ready
 * @description The <u>sw-media-preview-v2</u> component is used to show a preview of media objects.
 * @sw-package discovery
 * @example-type code-only
 * @component-example
 * <sw-media-preview-v2
 *      :source="item.id"
 *      :show-controls="true"
 *      :autoplay="false"
 *      :use-thumbnails="false">
 * </sw-media-preview-v2>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'feature',
    ],

    emits: [
        'click',
        'media-preview-play',
    ],

    placeholderThumbnailsBasePath: '/administration/administration/static/img/media-preview/',

    placeHolderThumbnails: {
        application: {
            'adobe.illustrator': 'icons-multicolor-file-thumbnail-ai',
            illustrator: 'icons-multicolor-file-thumbnail-ai',
            postscript: 'icons-multicolor-file-thumbnail-ai',
            msword: 'icons-multicolor-file-thumbnail-doc',
            'vnd.openxmlformats-officedocument.wordprocessingml.document': 'icons-multicolor-file-thumbnail-doc',
            pdf: 'icons-multicolor-file-thumbnail-pdf',
            'vnd.ms-excel': 'icons-multicolor-file-thumbnail-xls',
            'vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'icons-multicolor-file-thumbnail-xls',
            'vnd.ms-powerpoint': 'icons-multicolor-file-thumbnail-ppt',
            'vnd.openxmlformats-officedocument.presentationml.presentation': 'icons-multicolor-file-thumbnail-ppt',
            glb: 'icons-multicolor-file-thumbnail-glb',
            'octet-stream': 'icons-multicolor-file-thumbnail-glb',
        },
        video: {
            'x-msvideo': 'icons-multicolor-file-thumbnail-avi',
            quicktime: 'icons-multicolor-file-thumbnail-mov',
            mp4: 'icons-multicolor-file-thumbnail-mp4',
        },
        text: {
            csv: 'icons-multicolor-file-thumbnail-csv',
            plain: 'icons-multicolor-file-thumbnail-csv',
        },
        image: {
            gif: 'icons-multicolor-file-thumbnail-gif',
            jpeg: 'icons-multicolor-file-thumbnail-jpg',
            'svg+xml': 'icons-multicolor-file-thumbnail-svg',
        },
        model: {
            'gltf-binary': 'icons-multicolor-file-thumbnail-glb',
        },
    },

    props: {
        // eslint-disable-next-line vue/require-prop-types
        source: {
            required: true,
        },

        showControls: {
            type: Boolean,
            required: false,
            default: false,
        },

        autoplay: {
            type: Boolean,
            required: false,
            default: false,
        },

        transparency: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        useThumbnails: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        hideTooltip: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        mediaIsPrivate: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            trueSource: null,
            width: 0,
            dataUrl: '',
            urlPreviewFailed: false,
            imagePreviewFailed: false,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        mediaPreviewClasses() {
            return {
                'is--icon': this.isIcon,
                'is--no-media': !this.source,
            };
        },

        transparencyClass() {
            return {
                'shows--transparency': this.canBeTransparent,
            };
        },

        canBeTransparent() {
            if (!this.transparency) {
                return false;
            }

            return this.isIcon || this.mimeTypeGroup === 'image';
        },

        mimeType() {
            if (!this.trueSource) {
                return '';
            }

            if (this.trueSource instanceof File) {
                return this.trueSource.type;
            }

            if (this.trueSource instanceof URL) {
                return 'application/octet-stream';
            }

            return this.trueSource.mimeType;
        },

        mimeTypeGroup() {
            if (!this.mimeType) {
                return '';
            }

            return this.mimeType.split('/')[0];
        },

        isPlayable() {
            return isPlayableMediaFormat(this.mimeType);
        },

        showUnsupportedFormatWarning() {
            return shouldShowUnsupportedFormatWarning(this.mimeType);
        },

        isIcon() {
            return /.*svg.*/.test(this.mimeType);
        },

        placeholderIcon() {
            if (!this.mimeType) {
                return 'icons-multicolor-file-thumbnail-broken';
            }

            const mediaTypeIconGroup = this.$options.placeHolderThumbnails[this.mimeTypeGroup];
            if (mediaTypeIconGroup) {
                const mediaTypeIcon = mediaTypeIconGroup[`${this.mimeType.split('/')[1]}`];
                if (mediaTypeIcon) {
                    return mediaTypeIcon;
                }
            }

            return 'icons-multicolor-file-thumbnail-normal';
        },

        placeholderIconPath() {
            return `${this.$options.placeholderThumbnailsBasePath}${this.placeholderIcon}.svg`;
        },

        lockIsVisible() {
            return this.width > 40;
        },

        previewUrl() {
            if (!this.trueSource) {
                return '';
            }

            if (this.isFile) {
                this.getDataUrlFromFile();
                return this.dataUrl;
            }

            if (this.isUrl) {
                return this.trueSource.href;
            }

            if (this.isRelativePath) {
                return this.trueSource;
            }

            return this.trueSource.url;
        },

        isUrl() {
            return this.trueSource instanceof URL;
        },

        isFile() {
            return this.trueSource instanceof File;
        },

        isRelativePath() {
            return typeof this.trueSource === 'string';
        },

        alt() {
            if (this.trueSource.alt) {
                return this.trueSource.alt;
            }
            return this.trueSource.fileName;
        },

        mediaName() {
            if (!this.trueSource) {
                return this.$tc('global.sw-media-preview-v2.textNoMedia');
            }

            return this.mediaNameFilter(this.trueSource, this.trueSource.fileName);
        },

        mediaNameFilter() {
            return Filter.getByName('mediaName');
        },

        assetFilter() {
            return Filter.getByName('asset');
        },

        sourceSet() {
            if (this.isFile || this.isUrl || !this.trueSource) {
                return '';
            }

            return this.buildSourceSet(this.trueSource);
        },

        videoCoverMedia() {
            if (!this.trueSource || typeof this.trueSource !== 'object') {
                return null;
            }

            return this.trueSource.extensions?.videoCoverMedia ?? null;
        },

        videoCoverPoster() {
            return this.videoCoverMedia?.url ?? null;
        },

        hasVideoCover() {
            return Boolean(this.videoCoverPoster) && !this.mediaIsPrivate;
        },

        videoPreloadValue() {
            return this.hasVideoCover ? 'none' : 'metadata';
        },
    },

    watch: {
        source() {
            this.urlPreviewFailed = false;
            this.imagePreviewFailed = false;
            this.fetchSourceIfNecessary();
        },
        previewUrl(newUrl, oldUrl) {
            if (!newUrl || newUrl === oldUrl) {
                return;
            }

            this.reloadMediaElement();
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.beforeUnmountedComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.fetchSourceIfNecessary();
            EventBus.on('sw-media-library-item-updated', this.onMediaLibraryItemUpdated);
        },

        beforeUnmountedComponent() {
            EventBus.off('sw-media-library-item-updated', this.onMediaLibraryItemUpdated);
        },

        mountedComponent() {
            this.width = this.$el.offsetWidth;
        },

        async fetchSourceIfNecessary() {
            if (!this.source) {
                return;
            }

            if (typeof this.source !== 'string') {
                this.trueSource = this.source[0] ?? this.source;
                await this.ensureVideoCoverMedia();

                return;
            }

            try {
                this.trueSource = await this.mediaRepository.get(this.source, Context.api);
                await this.ensureVideoCoverMedia();
            } catch {
                this.trueSource = this.source;
            }
        },

        onPlayClick(originalDomEvent) {
            if (!(originalDomEvent.shiftKey || originalDomEvent.ctrlKey)) {
                originalDomEvent.stopPropagation();
                this.$emit('media-preview-play', {
                    originalDomEvent,
                    item: this.trueSource,
                });
            }
        },

        async getDataUrlFromFile() {
            if (this.mimeTypeGroup !== 'image') {
                return;
            }

            this.dataUrl = await fileReader.readAsDataURL(this.trueSource);
        },

        reloadMediaElement() {
            if (!this.isPlayable || (this.mimeTypeGroup !== 'video' && this.mimeTypeGroup !== 'audio')) {
                return;
            }

            this.$nextTick(() => {
                const element = this.$refs.mediaElement;

                if (typeof element?.load === 'function') {
                    element.load();
                }
            });
        },

        removeUrlPreview() {
            this.urlPreviewFailed = true;
        },

        showEvent() {
            if (!this.isFile) {
                this.imagePreviewFailed = true;
            }
        },

        onMediaLibraryItemUpdated(mediaId) {
            const currentMediaId = this.getCurrentMediaId();

            if (!currentMediaId || currentMediaId !== mediaId) {
                return;
            }

            this.fetchSourceIfNecessary();
        },

        getCurrentMediaId() {
            if (typeof this.source === 'string') {
                return this.source;
            }

            const entity = Array.isArray(this.source) ? this.source[0] : this.source;
            return entity?.id ?? this.trueSource?.id ?? null;
        },

        async ensureVideoCoverMedia() {
            if (!this.trueSource || typeof this.trueSource !== 'object') {
                return;
            }

            const coverMediaId = this.getVideoCoverMediaId(this.trueSource);

            if (!coverMediaId) {
                return;
            }

            const existingCover = this.trueSource.extensions?.videoCoverMedia;
            if (existingCover && existingCover.id === coverMediaId) {
                return;
            }

            try {
                const coverMedia = await this.mediaRepository.get(coverMediaId, Context.api);

                this.trueSource.extensions = {
                    ...(this.trueSource.extensions ?? {}),
                    videoCoverMedia: coverMedia,
                };
            } catch {
                // ignore fetch errors for cover preview
            }
        },

        getVideoCoverMediaId(mediaEntity) {
            const metaData = mediaEntity?.metaData;

            if (!metaData || typeof metaData !== 'object') {
                return null;
            }

            const videoMeta = metaData.video;
            if (!videoMeta || typeof videoMeta !== 'object') {
                return null;
            }

            const coverMediaId = videoMeta.coverMediaId;

            return typeof coverMediaId === 'string' ? coverMediaId : null;
        },

        buildSourceSet(media) {
            if (!media || media instanceof File || media instanceof URL || typeof media === 'string') {
                return '';
            }

            const thumbnails = Array.isArray(media.thumbnails) ? media.thumbnails : [];

            if (thumbnails.length === 0) {
                return '';
            }

            const sources = thumbnails.map((thumbnail) => {
                const url = this.feature.isActive('v6.8.0.0') ? thumbnail.url : encodeURI(thumbnail.url);

                return `${url} ${thumbnail.width}w`;
            });

            return sources.join(', ');
        },
    },
};
