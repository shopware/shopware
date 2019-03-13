import { State, Filter } from 'src/core/shopware';
import { fileReader } from 'src/core/service/util.service';
import template from './sw-media-preview.html.twig';
import './sw-media-preview.scss';

/**
 * @status ready
 * @description The <u>sw-media-preview</u> component is used to show a preview of media objects.
 * @example-type code-only
 * @component-example
 * <sw-media-preview :item="item" :showControls="true" :autoplay="false" :useThumbnails="false">
 * </sw-media-preview>
 */
export default {
    name: 'sw-media-preview',
    template,

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
        'application/pdf': 'file-thumbnail-pdf',
        'application/msword': 'file-thumbnail-doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'file-thumbnail-doc',
        'application/vnd.ms-excel': 'file-thumbnail-xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'file-thumbnail-xls',
        'application/svg': 'file-thumbnail-svg',
        'application/vnd.ms-powerpoint': 'file-thumbnail-ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'file-thumbnail-ppt',
        'application/svg+xml': 'file-thumbnail-svg'
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
            return State.getStore('media');
        },

        mediaPreviewClasses() {
            return {
                'is--icon': this.isIcon
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
                return 'file-thumbnail-mov';
            }

            if (this.mimeTypeGroup === 'audio') {
                return 'file-thumbnail-mp3';
            }

            return this.$options.placeHolderThumbnails[this.mimeType] || 'file-thumbnail-normal';
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

            const sources = this.trueSource.thumbnails.map((thumbnail) => {
                return `${thumbnail.url} ${thumbnail.width}w`;
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
        this.componentCreated();
    },

    mounted() {
        this.width = this.$el.offsetWidth;
    },

    methods: {
        componentCreated() {
            this.fetchSourceIfNecessary();
        },

        fetchSourceIfNecessary() {
            if (typeof this.source === 'string') {
                this.trueSource = this.mediaStore.getById(this.source);
                return;
            }

            this.trueSource = this.source;
        },

        onPlayClick(originalDomEvent) {
            if (!(originalDomEvent.shiftKey || originalDomEvent.ctrlKey)) {
                originalDomEvent.stopPropagation();
                this.$emit('sw-media-preview-play', {
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
};
