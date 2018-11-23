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
        catalogId: {
            required: false,
            type: String,
            default: ''
        },

        allowMultiSelect: {
            required: false,
            type: Boolean,
            default: true
        },

        compact: {
            required: false,
            type: Boolean,
            default: false
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

    methods: {
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
            const uploadTag = util.createId();

            newMediaFiles.forEach((file) => {
                this.addMediaEntityFromFile(file, uploadTag);
            });

            this.uploadStore.runUploads(uploadTag).then(() => {
                this.$emit('new-media-entity');
            });

            this.$refs.fileForm.reset();
        },

        addMediaEntityFromFile(file, tag) {
            const mediaEntity = this.createNewMedia(file.name);

            this.uploadStore.addUpload(tag, this.buildUpload(file, mediaEntity));
        },

        createMediaEntityFromUrl(url, fileExtension) {
            const mediaEntity = this.createNewMedia(this.getNameFromURL(url));

            const uploadTag = mediaEntity.id;

            this.uploadStore.addUpload(uploadTag, this.buildUpload(url, mediaEntity, fileExtension));

            this.uploadStore.runUploads(uploadTag).then(() => {
                this.$emit('new-media-entity');
            });
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
                mediaEntity.save().then(() => {
                    this.$emit('new-upload-started', mediaEntity);
                    return uploadFn(mediaEntity);
                }).then(() => {
                    this.notifyMediaUpload(mediaEntity, successMessage);
                }).catch(() => {
                    return this.cleanUpFailure(mediaEntity, failureMessage);
                });
            };
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

            mediaEntity.catalogId = this.catalogId;
            mediaEntity.name = name;

            return mediaEntity;
        },

        getNameFromURL(url) {
            return url.pathname.split('/').pop().split('.')[0];
        }
    }
});
