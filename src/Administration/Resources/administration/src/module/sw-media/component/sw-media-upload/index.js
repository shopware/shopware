import { Component, Mixin, State } from 'src/core/shopware';
import util, { fileReader } from 'src/core/service/util.service';
import template from './sw-media-upload.html.twig';
import './sw-media-upload.less';


Component.register('sw-media-upload', {
    template,

    inject: ['mediaService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        catalogId: {
            required: true,
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

        onUrlUpload({ url }) {
            this.createMediaEntityFromUrl(url);
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
            this.uploadStore.addUpload(tag, () => {
                const mediaEntity = this.createNewMedia(file.name);

                return mediaEntity.save().then(() => {
                    return fileReader.readAsArrayBuffer(file).then((buffer) => {
                        return this.mediaService.uploadMediaById(
                            mediaEntity.id,
                            file.type,
                            buffer,
                            file.name.split('.').pop()
                        );
                    });
                }).then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-media.upload.notificationSuccess'),
                    });
                }).catch(() => {
                    this.cleanUpFailure(mediaEntity);
                });
            });
        },

        createMediaEntityFromUrl(url) {
            const mediaEntity = this.createNewMedia(this.getNameFromURL(url));

            mediaEntity.save().then(() => {
                const uploadTag = mediaEntity.id;
                const fileExtension = this.getFileExtensionFromURL(url);
                const mediaService = this.mediaService;

                this.uploadStore.addUpload(uploadTag, () => {
                    return mediaService.uploadMediaFromUrl(mediaEntity.id, url.href, fileExtension).then(() => {
                        this.createNotificationSuccess({
                            message: this.$tc('sw-media.upload.notificationSuccess'),
                        });
                    }).catch(() => {
                        return this.cleanUpFailure(mediaEntity);
                    });
                });

                this.uploadStore.runUploads(uploadTag).then(() => {
                    this.$emit('new-media-entity');
                });
                this.closeUrlModal();
            });
        },

        cleanUpFailure(mediaEntity) {
            this.createNotificationError({
                message: this.$tc('sw-media.upload.notificationFailure', 0, { mediaName: mediaEntity.name }),
            });
            // delete media entity on failed upload
            return this.mediaItemStore.getByIdAsync(mediaEntity.id).then((media) => {
                media.delete(true);
            });
        },

        createNewMedia(name) {
            const mediaEntity = this.mediaItemStore.create();

            mediaEntity.catalogId = this.catalogId;
            mediaEntity.name = name;

            return mediaEntity;
        },

        getNameFromURL(url) {
            return url.pathname.split('/').pop().split('.')[0];
        },

        getFileExtensionFromURL(url) {
            return url.pathname.split('.').pop();
        }
    }
});
