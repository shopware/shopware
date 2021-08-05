import template from './sw-media-preview-v2.html.twig';
import './sw-media-preview-v2.scss';

const { Component, Context, Filter } = Shopware;
const { fileReader } = Shopware.Utils;

/**
 * @status ready
 * @description The <u>sw-media-preview-v2</u> component is used to show a preview of media objects.
 * @example-type code-only
 * @component-example
 * <sw-media-preview-v2
 *      :source="item.id"
 *      :show-controls="true"
 *      :autoplay="false"
 *      :use-thumbnails="false">
 * </sw-media-preview-v2>
 */
Component.register('sw-media-preview-v2', {
    template,

    inject: ['repositoryFactory'],

    playableVideoFormats: [
        'video/mp4',
        'video/ogg',
        'video/webm',
    ],

    playableAudioFormats: [
        'audio/mp3',
        'audio/mpeg',
        'audio/ogg',
        'audio/wav',
    ],

    placeHolderThumbnails: {
        'application/pdf': 'multicolor-file-thumbnail-pdf',
        'application/msword': 'multicolor-file-thumbnail-doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'multicolor-file-thumbnail-doc',
        'application/vnd.ms-excel': 'multicolor-file-thumbnail-xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'multicolor-file-thumbnail-xls',
        'application/svg': 'multicolor-file-thumbnail-svg',
        'application/vnd.ms-powerpoint': 'multicolor-file-thumbnail-ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'multicolor-file-thumbnail-ppt',
        'application/svg+xml': 'multicolor-file-thumbnail-svg',
    },

    props: {
        // FIXME: add type to property
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
            default: true,
        },

        useThumbnails: {
            type: Boolean,
            required: false,
            default: true,
        },

        hideTooltip: {
            type: Boolean,
            required: false,
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
            if (this.$options.playableVideoFormats.includes(this.mimeType)) {
                return true;
            }

            if (this.$options.playableAudioFormats.includes(this.mimeType)) {
                return true;
            }

            return false;
        },

        isIcon() {
            return /.*svg.*/.test(this.mimeType);
        },

        placeholderIcon() {
            if (this.mimeTypeGroup === 'video') {
                return 'multicolor-file-thumbnail-mov';
            }

            if (this.mimeTypeGroup === 'audio') {
                return 'multicolor-file-thumbnail-mp3';
            }

            return this.$options.placeHolderThumbnails[this.mimeType] || 'multicolor-file-thumbnail-normal';
        },

        previewUrl() {
            if (this.isFile) {
                this.getDataUrlFromFile();
                return this.dataUrl;
            }

            if (this.isUrl) {
                return this.trueSource.href;
            }

            return this.trueSource.url;
        },

        isUrl() {
            return this.trueSource instanceof URL;
        },

        isFile() {
            return this.trueSource instanceof File;
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

        sourceSet() {
            if (this.isFile || this.isUrl) {
                return '';
            }

            if (this.trueSource.thumbnails.length === 0) {
                return '';
            }

            const sources = [];
            this.trueSource.thumbnails.forEach((thumbnail) => {
                const encoded = encodeURI(thumbnail.url);
                sources.push(`${encoded} ${thumbnail.width}w`);
            });

            return sources.join(', ');
        },
    },

    watch: {
        source() {
            this.urlPreviewFailed = false;
            this.imagePreviewFailed = false;
            this.fetchSourceIfNecessary();
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.fetchSourceIfNecessary();
        },

        mountedComponent() {
            this.width = this.$el.offsetWidth;
        },

        async fetchSourceIfNecessary() {
            if (!this.source) {
                return;
            }

            if (typeof this.source === 'string') {
                this.trueSource = await this.mediaRepository.get(this.source, Context.api);
            } else {
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

        removeUrlPreview() {
            this.urlPreviewFailed = true;
        },

        showEvent() {
            if (!this.isFile) {
                this.imagePreviewFailed = true;
            }
        },
    },
});
