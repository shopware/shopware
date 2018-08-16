import { Component } from 'src/core/shopware';
import template from './sw-media-upload-container.html.twig';
import './sw-media-upload-container.less';

Component.register('sw-media-upload-container', {
    template,

    props: {
        multipleFiles: {
            type: Boolean,
            required: false,
            default: true
        },

        title: {
            type: String,
            required: false
        }
    },

    data() {
        return {
            showUrlModal: false,
            files: [],
            urls: [],
            mediaEntities: []
        };
    },

    methods: {
        resetArrays() {
            this.files = [];
            this.urls = [];
            this.mediaEntities = [];
        },

        addFile() {
            this.$refs.fileInput.click();
        },

        emitOpenMedia(originalDomEvent) {
            this.$emit('sw-media-upload-container-open-media-event', {
                originalDomEvent
            });
        },

        emitAddedData(originalDomEvent) {
            this.$emit('sw-media-upload-container-publish-selection', {
                originalDomEvent,
                files: this.files,
                urls: this.urls,
                mediaEntities: this.mediaItems
            });
        },

        handleFileUpload(event) {
            if (!this.multipleFiles) {
                this.resetArrays();
            }

            this.files = Array.from(this.$refs.fileInput.files);
            this.emitAddedData(event);
        },

        openUrlDialog() {
            this.showUrlDialog(true);
        },

        closeUrlDialog() {
            this.showUrlDialog(false);
        },

        showUrlDialog(showUrlDialog) {
            this.showUrlModal = showUrlDialog;
        },

        uploadFromUrl({ originalDomEvent, url }) {
            if (!this.multipleFiles) {
                this.resetArrays();
            }

            this.urls.push(url);
            this.emitAddedData(originalDomEvent);
        }
    }
});
