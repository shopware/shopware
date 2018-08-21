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

            return (regEx.test(filePath));
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
        }
    }
});
