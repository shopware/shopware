import { Component, Mixin, State } from 'src/core/shopware';
import util, { fileReader } from 'src/core/service/util.service';
import template from './sw-media-upload-button.html.twig';
import './sw-media-upload-button.less';

/**
 * @private
 */
Component.register('sw-media-upload-button', {
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
        }
    },

    data() {
        return {
            multiSelect: this.allowMultiSelect,
            showUrlInput: false,
            previewMediaEntity: null
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
                this.$tc('global.sw-media-upload-button.buttonSwitchToFileUpload') :
                this.$tc('global.sw-media-upload-button.buttonSwitchToUrlUpload');
        },

        isMediaSidebarAvailable() {
            return Object.keys(this.$listeners).includes('sw-media-upload-button-open-sidebar');
        }
    },

    beforeDestroy() {
        this.onBeforeDestroy();
    },

    methods: {
        onBeforeDestroy() {
            this.uploadStore.removeByTag(this.uploadTag);
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
            this.$emit('sw-media-upload-button-open-sidebar');
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
            let newMediaFiles = Array.from(this.$refs.fileInput.files);
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
            this.$refs.fileForm.reset();
        },

        /*
         * Helper functions
         */
        getMediaEntityForUpload() {
            return this.mediaItemStore.create();
        },

        buildFileUpload(file, mediaEntity) {
            const successMessage = this.$tc('global.sw-media-upload-button.notificationSuccess');
            const failureMessage = this.$tc('global.sw-media-upload-button.notificationFailure');

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
            const successMessage = this.$tc('global.sw-media-upload-button.notificationSuccess');
            const failureMessage = this.$tc('global.sw-media-upload-button.notificationFailure');

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
