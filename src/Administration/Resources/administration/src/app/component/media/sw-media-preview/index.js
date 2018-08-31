import { Component } from 'src/core/shopware';
import template from './sw-media-preview.html.twig';
import './sw-media-preview.less';

Component.register('sw-media-preview', {
    template,

    props: {
        item: {
            required: true,
            type: Object
        },

        altText: {
            type: String,
            required: false,
            default: ''
        },

        downloadable: {
            type: Boolean,
            required: false,
            default: true
        }
    },

    computed: {
        checkForFileTypeImage() {
            const filePath = this.item.mimeType;
            const regEx = /^image\/+/;

            return (regEx.test(filePath));
        },

        checkForFileTypeVideo() {
            const filePath = this.item.mimeType;
            const regEx = /^video\/+/;

            return (regEx.test(filePath) && this.isPlayable());
        },

        checkForFileTypeAudio() {
            const filePath = this.item.mimeType;
            const regEx = /^audio\/+/;

            return (regEx.test(filePath));
        },

        checkForMimeType() {
            const fileExtensions = {
                'application/pdf': '.pdf',
                'application/msword': '.doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document': '.docx',
                'application/vnd.ms-excel': '.xls',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': '.xlsx',
                'application/vnd.ms-powerpoint': '.ppt',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation': '.pptx',
                'application/svg': '.svg'
            };

            return fileExtensions[this.item.mimeType] || 'unknown';
        },

        placeholderIcon() {
            const regEx = /^video\/+/;

            if (regEx.test(this.item.mimeType)) {
                // show movie placeholder image if video format is not playable
                return 'file-thumbnail-mov';
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

        imageURLFromItem() {
            return `${this.item.url}?${Date.now()}`;
        }
    },

    methods: {
        isPlayable() {
            const playableFormats = {
                'video/mp4': true,
                'video/webm': true
            };

            return playableFormats[this.item.mimeType] || false;
        }
    }
});
