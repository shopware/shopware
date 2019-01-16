import { Component } from 'src/core/shopware';
import template from './sw-media-preview.html.twig';
import './sw-media-preview.less';

/**
 * @status ready
 * @description The <u>sw-media-preview</u> component is used to show a preview of media objects.
 * @example-type code-only
 * @component-example
 * <sw-media-preview :item="item" :showControls="true" :autoplay="false" :useThumbnails="false">
 * </sw-media-preview>
 */
Component.register('sw-media-preview', {
    template,

    props: {
        item: {
            required: true,
            type: Object

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
        }
    },

    computed: {
        mediaPreviewClasses() {
            return {
                'shows--transparency': this.checkForFileTypeImage && this.transparency,
                'is--icon': this.checkForFileTypeSvg
            };
        },

        transparencyClass() {
            return {
                'shows--transparency': this.checkForFileTypeImage && this.transparency
            };
        },

        checkForFileTypeImage() {
            return this.isFileType('image');
        },

        checkForFileTypeVideo() {
            return this.isFileType('video') && this.isVideoPlayable();
        },

        checkForFileTypeAudio() {
            return this.isFileType('audio') && this.isAudioPlayable();
        },

        checkForFileTypeSvg() {
            const regEx = /.*svg.*/;
            return regEx.test(this.item.mimeType);
        },

        placeholderIcon() {
            if (!this.item.hasFile) {
                // ToDo change this if design has finished an broken file icon
                return 'file-thumbnail-normal';
            }

            if (this.isFileType('video')) {
                return 'file-thumbnail-mov';
            }

            if (this.isFileType('audio')) {
                return 'file-thumbnail-mp3';
            }

            const fileExtensions = {
                'application/pdf': 'file-thumbnail-pdf',
                'application/msword': 'file-thumbnail-doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'file-thumbnail-doc',
                'application/vnd.ms-excel': 'file-thumbnail-xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'file-thumbnail-xls',
                'application/svg': 'file-thumbnail-svg',
                'application/vnd.ms-powerpoint': 'file-thumbnail-ppt',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'file-thumbnail-ppt',
                'application/svg+xml': 'file-thumbnail-svg'
            };

            return fileExtensions[this.item.mimeType] || 'file-thumbnail-normal';
        },

        urlFromItem() {
            if (this.item.dataUrl) {
                return this.item.dataUrl;
            }

            if (this.useThumbnails && this.item.thumbnails && this.item.thumbnails.length > 0) {
                const thumbnails = this.item.thumbnails.filter((thumb) => {
                    return thumb.height === 300;
                });
                if (thumbnails.length > 0) {
                    return thumbnails[0].url;
                }

                return this.item.thumbnails[0].url;
            }

            return this.item.url;
        }
    },

    methods: {
        onPlayClick(originalDomEvent) {
            if (!(originalDomEvent.shiftKey || originalDomEvent.ctrlKey)) {
                originalDomEvent.stopPropagation();
                this.$emit('sw-media-preview-play', {
                    originalDomEvent,
                    item: this.item
                });
            }
        },

        isVideoPlayable() {
            return [
                'video/mp4',
                'video/ogg',
                'video/webm'
            ].includes(this.item.mimeType);
        },

        isAudioPlayable() {
            return [
                'audio/mp3',
                'audio/mpeg',
                'audio/ogg',
                'audio/wav'
            ].includes(this.item.mimeType);
        },

        isFileType(filetype) {
            const regEx = new RegExp(`^${filetype}\\/+`);

            return regEx.test(this.item.mimeType);
        }
    }
});
