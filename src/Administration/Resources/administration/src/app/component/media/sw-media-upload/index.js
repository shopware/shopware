import { Component, Mixin, State } from 'src/core/shopware';
import util, { fileReader } from 'src/core/service/util.service';
import template from './sw-media-upload.html.twig';
import './sw-media-upload.less';

/**
 * @private
 */
Component.register('sw-media-upload', {
    template,

    inject: ['mediaUploadService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        uploadTag: {
            required: false,
            type: String,
            default: util.createId()
        },

        allowMultiSelect: {
            required: false,
            type: Boolean,
            default: true
        },

        variant: {
            type: String,
            required: true,
            validator(value) {
                return ['compact', 'regular'].includes(value);
            }
        },

        autoUpload: {
            required: false,
            type: Boolean,
            default: true
        },

        caption: {
            required: false,
            type: String
        },

        scrollTarget: {
            required: false,
            type: HTMLElement,
            default: null
        }
    },

    data() {
        return {
            multiSelect: this.allowMultiSelect,
            showUrlInput: false,
            previewMediaEntity: null,
            isDragActive: false
        };
    },

    computed: {
        mediaItemStore() {
            return State.getStore('media');
        },

        uploadStore() {
            return State.getStore('upload');
        },

        showPreview() {
            return !(this.autoUpload || this.multiSelect);
        },

        hasPreviewFile() {
            return this.previewMediaEntity !== null;
        },

        toggleButtonCaption() {
            return this.showUrlInput ?
                this.$tc('global.sw-media-upload.buttonSwitchToFileUpload') :
                this.$tc('global.sw-media-upload.buttonSwitchToUrlUpload');
        },

        hasOpenSidebarButtonListener() {
            return Object.keys(this.$listeners).includes('sw-media-upload-open-sidebar');
        },

        isDragActiveClass() {
            return {
                'is--active': this.isDragActive
            };
        }
    },

    mounted() {
        this.onMounted();
    },

    beforeDestroy() {
        this.onBeforeDestroy();
    },

    methods: {
        onMounted() {
            if (this.$refs.dropzone) {
                ['dragover', 'drop'].forEach((event) => {
                    window.addEventListener(event, this.stopEventPropagation, false);
                });
                this.$refs.dropzone.addEventListener('drop', this.onDrop);

                window.addEventListener('dragenter', this.onDragEnter);
                window.addEventListener('dragleave', this.onDragLeave);
            }
        },

        onBeforeDestroy() {
            this.uploadStore.removeByTag(this.uploadTag);

            ['dragover', 'drop'].forEach((event) => {
                window.addEventListener(event, this.stopEventPropagation, false);
            });

            window.removeEventListener('dragenter', this.onDragEnter);
            window.removeEventListener('dragleave', this.onDragLeave);
        },

        /*
         * Drop Handler
         */
        onDrop(event) {
            const newMediaFiles = Array.from(event.dataTransfer.files);
            this.handleUpload(newMediaFiles);

            this.isDragActive = false;
        },

        onDragEnter() {
            if (this.scrollTarget !== null && !this.isElementInViewport(this.$refs.dropzone)) {
                this.scrollTarget.scrollIntoView();
            }

            this.isDragActive = true;
        },

        onDragLeave(event) {
            if (event.screenX === 0 && event.screenY === 0) {
                this.isDragActive = false;
            }
        },

        stopEventPropagation(event) {
            event.preventDefault();
            event.stopPropagation();
        },

        /*
         * Click handler
         */
        onClickUpload() {
            this.$refs.fileInput.click();
        },

        openUrlModal() {
            this.showUrlInput = true;
        },

        closeUrlModal() {
            this.showUrlInput = false;
        },

        toggleShowUrlFields() {
            this.showUrlInput = !this.showUrlInput;
        },

        onClickOpenMediaSidebar() {
            this.$emit('sw-media-upload-open-sidebar');
        },

        /*
         * entry points
         */
        onUrlUpload({ url, fileExtension }) {
            if (!this.multiSelect) {
                this.uploadStore.removeByTag(this.uploadTag);
                this.createPreviewFromUrl(url);
            }

            const mediaEntity = this.getMediaEntityForUpload();
            this.uploadStore.addUpload(
                this.uploadTag,
                this.buildUrlUpload(
                    url,
                    fileExtension,
                    mediaEntity
                )
            );
            this.$emit('new-upload', { uploadTag: this.uploadTag, mediaEntity, src: url });

            if (this.autoUpload) {
                this.uploadStore.runUploads(this.uploadTag).then(() => {
                    this.$emit('new-media-entity');
                });
            }
            this.closeUrlModal();
        },

        onFileInputChange() {
            const newMediaFiles = Array.from(this.$refs.fileInput.files);
            this.handleUpload(newMediaFiles);
            this.$refs.fileForm.reset();
        },

        /*
         * Helper functions
         */
        isElementInViewport(el) {
            const rect = el.getBoundingClientRect();

            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        },

        handleUpload(newMediaFiles) {
            if (!this.multiSelect) {
                this.uploadStore.removeByTag(this.uploadTag);

                const fileToUpload = newMediaFiles.pop();
                this.createPreviewFromFile(fileToUpload);
                newMediaFiles = [fileToUpload];
            }

            newMediaFiles.forEach((file) => {
                const mediaEntity = this.getMediaEntityForUpload();
                this.uploadStore.addUpload(this.uploadTag, this.buildFileUpload(file, mediaEntity));
                this.$emit('new-upload', { uploadTag: this.uploadTag, mediaEntity, src: file });
            });

            if (this.autoUpload) {
                this.uploadStore.runUploads(this.uploadTag).then(() => {
                    this.$emit('new-media-entity');
                });
            }
        },

        getMediaEntityForUpload() {
            return this.mediaItemStore.create();
        },

        buildFileUpload(file, mediaEntity) {
            const successMessage = this.$tc('global.sw-media-upload.notificationSuccess');
            const failureMessage = this.$tc('global.sw-media-upload.notificationFailure');

            return () => {
                this.synchronizeMediaEntity(mediaEntity).then(() => {
                    this.$emit('new-upload-started', mediaEntity);
                    return this.mediaUploadService.uploadFileToMedia(file, mediaEntity);
                }).then(() => {
                    this.notifyMediaUpload(mediaEntity, successMessage);
                }).catch(() => {
                    return this.cleanUpFailure(mediaEntity, failureMessage);
                });
            };
        },

        buildUrlUpload(url, fileExtension, mediaEntity) {
            const successMessage = this.$tc('global.sw-media-upload.notificationSuccess');
            const failureMessage = this.$tc('global.sw-media-upload.notificationFailure');

            return () => {
                this.synchronizeMediaEntity(mediaEntity).then(() => {
                    this.$emit('new-upload-started', mediaEntity);
                    return this.mediaUploadService.uploadUrlToMedia(url, mediaEntity, fileExtension);
                }).then(() => {
                    this.notifyMediaUpload(mediaEntity, successMessage);
                }).catch(() => {
                    return this.cleanUpFailure(mediaEntity, failureMessage);
                });
            };
        },

        createPreviewFromFile(file) {
            fileReader.readAsDataURL(file).then((dataUrl) => {
                this.previewMediaEntity = {
                    dataUrl,
                    mimeType: file.type
                };
            });
        },

        createPreviewFromUrl(url) {
            this.previewMediaEntity = {
                url: url.href,
                mimeTyp: 'image/*'
            };
        },

        synchronizeMediaEntity(mediaEntity) {
            return this.mediaItemStore.getByIdAsync(mediaEntity.id).catch(() => {
                return mediaEntity.save();
            });
        },

        notifyMediaUpload(mediaEntity, message) {
            this.mediaItemStore.getByIdAsync(mediaEntity.id).then((media) => {
                this.createNotificationSuccess({ message });
                this.$emit('media-upload-success', media);
            });
        },

        cleanUpFailure(mediaEntity, message) {
            this.createNotificationError({ message });
            mediaEntity.delete(true);
        }
    }
});
