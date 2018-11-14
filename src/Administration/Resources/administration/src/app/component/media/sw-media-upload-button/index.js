import { Component, Mixin, State } from 'src/core/shopware';
import util from 'src/core/service/util.service';
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
            showUrlModal: false
        };
    },

    computed: {
        mediaItemStore() {
            return State.getStore('media');
        },

        uploadStore() {
            return State.getStore('upload');
        }
    },

    beforeDestroy() {
        this.onBeforeDestroy();
    },

    methods: {

        onBeforeDestroy() {
            this.uploadStore.removeByTag(this.uploadTag);
        },

        onClickUpload() {
            this.$refs.fileInput.click();
        },

        openUrlModal() {
            this.showUrlModal = true;
        },

        closeUrlModal() {
            this.showUrlModal = false;
        },

        onUrlUpload({ url, fileExtension }) {
            this.createMediaEntityFromUrl(url, fileExtension);
        },

        onFileInputChange() {
            const newMediaFiles = Array.from(this.$refs.fileInput.files);
            const uploadTag = this.uploadTag;

            newMediaFiles.forEach((file) => {
                this.addMediaEntityFromFile(file, uploadTag);
            });

            if (this.autoUpload) {
                this.uploadStore.runUploads(uploadTag).then(() => {
                    this.$emit('new-media-entity');
                });
            }
            this.$refs.fileForm.reset();
        },

        addMediaEntityFromFile(file) {
            const mediaEntity = this.createNewMedia(file.name);

            const upload = this.uploadStore.addUpload(this.uploadTag, this.buildUpload(file, mediaEntity));

            this.$emit('new-upload', { upload, uploadTag: this.uploadTag, mediaEntity, src: file });
        },

        createMediaEntityFromUrl(url, fileExtension) {
            const mediaEntity = this.createNewMedia(this.getNameFromURL(url));

            const upload = this.uploadStore.addUpload(this.uploadTag, this.buildUpload(url, mediaEntity, fileExtension));

            this.$emit('new-upload', { upload, uploadTag: this.uploadTag, mediaEntity, src: url });

            if (this.autoUpload) {
                this.uploadStore.runUploads(this.uploadTag).then(() => {
                    this.$emit('new-media-entity');
                });
            }
            this.closeUrlModal();
        },

        buildUpload(source, mediaEntity, fileExtension = '') {
            let uploadFn = null;

            if (source instanceof URL) {
                uploadFn = (media) => { return this.mediaUploadService.uploadUrlToMedia(source, media, fileExtension); };
            } else if (source instanceof File) {
                uploadFn = (media) => { return this.mediaUploadService.uploadFileToMedia(source, media); };
            } else {
                throw new Error('Media source must be a URL object or a File object');
            }

            const successMessage = this.$tc('sw-media.upload.notificationSuccess');
            const failureMessage = this.$tc('sw-media.upload.notificationFailure', 0, { mediaName: mediaEntity.name });

            return () => {
                this.synchronizeMediaEntity(mediaEntity).then(() => {
                    this.$emit('new-upload-started', mediaEntity);
                    return uploadFn(mediaEntity);
                }).then(() => {
                    this.notifyMediaUpload(mediaEntity, successMessage);
                }).catch(() => {
                    return this.cleanUpFailure(mediaEntity, failureMessage);
                });
            };
        },

        synchronizeMediaEntity(mediaEntity) {
            return this.mediaItemStore.getByIdAsync(mediaEntity.id).catch(() => {
                return mediaEntity.save();
            });
        },

        notifyMediaUpload(mediaEntity, message) {
            this.createNotificationSuccess({ message });
            this.mediaItemStore.getByIdAsync(mediaEntity.id).then((media) => {
                this.$emit('media-upload-success', media);
            });
        },

        cleanUpFailure(mediaEntity, message) {
            this.createNotificationError({ message });
            mediaEntity.delete(true);
        },

        createNewMedia(name) {
            const mediaEntity = this.mediaItemStore.create();

            mediaEntity.name = name;

            return mediaEntity;
        },

        getNameFromURL(url) {
            return url.pathname.split('/').pop().split('.')[0];
        }
    }
});
