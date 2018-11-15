import { Component, Mixin, State, Filter } from 'src/core/shopware';
import { fileReader } from 'src/core/service/util.service';
import template from './sw-media-modal-replace.html.twig';
import './sw-media-modal-replace.less';

/**
 * @private
 */
Component.register('sw-media-modal-replace', {
    template,

    inject: ['mediaUploadService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        itemToReplace: {
            type: Object,
            required: false
        }
    },

    data() {
        return {
            uploadData: null,
            previewMediaEntity: null
        };
    },

    computed: {
        isUploadDataSet() {
            return this.uploadData !== null;
        },

        mediaItemStore() {
            return State.getStore('media');
        },

        fileNameFilter() {
            return Filter.getByName('pathToFileName');
        }
    },

    methods: {
        emitCloseReplaceModal() {
            this.$emit('sw-media-modal-replace-close');
        },

        onClickUpload() {
            this.$refs.fileInput.click();
        },

        onFileInputChange() {
            const file = Array.from(this.$refs.fileInput.files).pop();
            this.$refs.inputForm.reset();

            this.previewMediaEntity = null;
            this.uploadData = file;

            fileReader.readAsDataURL(file).then((result) => {
                this.previewMediaEntity = {
                    name: this.uploadData.name,
                    mimeType: this.uploadData.type,
                    dataUrl: result
                };
            });
        },

        replaceMediaItem() {
            this.replaceMediaFromFile();
        },

        replaceMediaFromFile() {
            const notificationSuccess = this.$tc('global.sw-media-modal-replace.notificationSuccess');
            const notificationError = this.$tc(
                'global.sw-media-modal-replace.notificationFailure',
                1,
                { mediaName: this.fileNameFilter(this.itemToReplace.fileName) }
            );

            this.itemToReplace.isLoading = true;
            this.mediaUploadService.uploadFileToMedia(this.uploadData, this.itemToReplace)
                .then(() => {
                    this.mediaItemStore.getByIdAsync(this.itemToReplace.id).then(() => {
                        this.createNotificationSuccess({
                            message: notificationSuccess
                        });
                    });
                })
                .catch(() => {
                    this.itemToReplace.isLoading = false;
                    this.createNotificationError({
                        message: notificationError
                    });
                });

            this.emitCloseReplaceModal();
        },

        removeSelectedFile() {
            this.uploadData = null;
            this.previewMediaEntity = null;
        }
    }
});
