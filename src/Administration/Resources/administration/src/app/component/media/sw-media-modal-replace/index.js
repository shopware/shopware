import { Component } from 'src/core/shopware';
import { debug, fileReader } from 'src/core/service/util.service';
import template from './sw-media-modal-replace.html.twig';
import './sw-media-modal-replace.less';

Component.register('sw-media-modal-replace', {
    template,

    inject: ['mediaService'],

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
        showReplaceModal() {
            return this.itemToReplace !== null;
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
            if (this.uploadData === null) {
                return;
            }

            if (this.uploadData.type === 'URL') {
                this.replaceMediaFromUrl();
            }

            if (this.uploadData.type === 'file') {
                this.replaceMediaFromFile();
            }

            this.uploadData = null;
        },

        replaceMediaFromUrl() {
            debug.warn('Uploading from Url is not supported right now');
        },

        replaceMediaFromFile() {
            const mediaId = this.itemToReplace.id;
            const mimeType = this.uploadData.data.type;
            const fileExtension = this.uploadData.data.name.split('.').pop();

            const replaceFromFile = fileReader.readAsArrayBuffer(this.uploadData.data).then((fileAsArray) => {
                return this.mediaService.uploadMediaById(
                    mediaId,
                    mimeType,
                    fileAsArray,
                    fileExtension
                );
            });

            this.emitReplaceStarted(replaceFromFile, this.itemToReplace);
        },

        emitReplaceStarted(itemUpload, file) {
            this.$emit('sw-media-modal-replace-confirmed', itemUpload, file);
        },

        removeSelectedFile() {
            this.uploadData = null;
            this.previewMediaEntity = null;
        }
    }
});
