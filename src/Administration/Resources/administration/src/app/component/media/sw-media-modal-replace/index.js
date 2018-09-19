import { Component, Mixin } from 'src/core/shopware';
import { debug, fileReader } from 'src/core/service/util.service';
import template from './sw-media-modal-replace.html.twig';
import './sw-media-modal-replace.less';

Component.register('sw-media-modal-replace', {
    template,

    inject: ['mediaService'],

    mixins: [Mixin.getByName('notification')],

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

            this.previewMediaEntity = null;
            this.uploadData = {
                type: 'file',
                data: file
            };

            fileReader.readAsDataURL(file).then((result) => {
                this.previewMediaEntity = {
                    name: this.uploadData.data.name,
                    mimeType: this.uploadData.data.type,
                    dataUrl: result
                };
            });
        },

        replaceMediaItem() {
            if (this.uploadData.type === 'URL') {
                this.replaceMediaFromUrl();
            }

            if (this.uploadData.type === 'file') {
                this.replaceMediaFromFile();
            }
        },

        replaceMediaFromUrl() {
            debug.warn('Uploading from Url is not supported right now');
        },

        replaceMediaFromFile() {
            const mimeType = this.uploadData.data.type;
            const fileExtension = this.uploadData.data.name.split('.').pop();
            const notificationSuccess = this.$tc('global.sw-media-modal-replace.notificationSuccess');
            const notificationError = this.$tc(
                'global.sw-media-modal-replace.notificationFailure',
                1,
                { mediaName: this.itemToReplace.name }
            );

            fileReader.readAsArrayBuffer(this.uploadData.data).then((fileAsArray) => {
                this.itemToReplace.isLoading = true;
                this.mediaService.uploadMediaById(
                    this.itemToReplace.id,
                    mimeType,
                    fileAsArray,
                    fileExtension
                ).then(() => {
                    this.itemToReplace.url = `${this.itemToReplace.url}?${Date.now()}`;
                    this.itemToReplace.isLoading = false;
                    this.createNotificationSuccess({
                        message: notificationSuccess
                    });
                }).catch(() => {
                    this.itemToReplace.isLoading = false;
                    this.createNotificationError({
                        message: notificationError
                    });
                });

                this.emitCloseReplaceModal();
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('global.sw-media-modal-replace.notificationFileReaderError')
                });
                this.removeSelectedFile();
            });
        },

        removeSelectedFile() {
            this.uploadData = null;
            this.previewMediaEntity = null;
        }
    }
});
