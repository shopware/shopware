import template from './sw-media-preview.html.twig';
import './sw-media-preview.scss';

const { Component, StateDeprecated, Filter } = Shopware;
const { fileReader } = Shopware.Utils;

/**
 * @deprecated tag:v6.4.0
 * @status ready
 * @description The <u>sw-media-preview</u> component is used to show a preview of media objects.
 * @example-type code-only
 * @component-example
 * <sw-media-preview :item="item" :showControls="true" :autoplay="false" :useThumbnails="false">
 * </sw-media-preview>
 */
Component.register('sw-media-preview', {
    template,

    deprecated: {
        version: '6.4.0',
        comment: 'Use sw-media-preview-v2 instead'
    },

    playableVideoFormats: [
        'video/mp4',
        'video/ogg',
        'video/webm'
    ],

    playableAudioFormats: [
        'audio/mp3',
        'audio/mpeg',
        'audio/ogg',
        'audio/wav'
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
        'application/svg+xml': 'multicolor-file-thumbnail-svg'
    },

    props: {
        source: {
            required: true
        },

        showControls: {
            type: Boolean,
            required: false,
            default: false
        },

        autoplay: {
            type: Boolean,
            required: false,
            default: false
        },

        transparency: {
            type: Boolean,
            required: false,
            default: true
        },

        useThumbnails: {
            type: Boolean,
            required: false,
            default: true
        },

        hideTooltip: {
            type: Boolean,
            required: false,
            default: true
        },

        mediaIsPrivate: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            trueSource: null,
            width: 0,
            dataUrl: '',
            urlPreviewFailed: false,
            imagePreviewFailed: false
        };
    },

    computed: {
        mediaStore() {
            return StateDeprecated.getStore('media');
        },

        mediaPreviewClasses() {
            return {
                'is--icon': this.isIcon,
                'is--no-media': !this.source
            };
        },

        transparencyClass() {
            return {
                'shows--transparency': this.canBeTransparent
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
                return this.$tc('global.sw-media-preview.textNoMedia');
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
        }
    },

    watch: {
        source() {
            this.urlPreviewFailed = false;
            this.imagePreviewFailed = false;
            this.fetchSourceIfNecessary();
        }
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

        fetchSourceIfNecessary() {
            if (!this.source) {
                return;
            }

            if (typeof this.source === 'string') {
                this.mediaStore.getByIdAsync(this.source).then((media) => {
                    this.trueSource = media;
                });
                return;
            }

            this.trueSource = this.source;
        },

        onPlayClick(originalDomEvent) {
            if (!(originalDomEvent.shiftKey || originalDomEvent.ctrlKey)) {
                originalDomEvent.stopPropagation();
                this.$emit('media-preview-play', {
                    originalDomEvent,
                    item: this.trueSource
                });
            }
        },

        getDataUrlFromFile() {
            if (this.mimeTypeGroup !== 'image') {
                return;
            }

            fileReader.readAsDataURL(this.trueSource).then((dataUrl) => {
                this.dataUrl = dataUrl;
            });
        },

        removeUrlPreview() {
            this.urlPreviewFailed = true;
        },

        showEvent() {
            if (!this.isFile) {
                this.imagePreviewFailed = true;
            }
        }
    }
});
