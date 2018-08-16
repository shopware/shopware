import { Component } from 'src/core/shopware';
import { debug, fileReader } from 'src/core/service/util.service';
import template from './sw-media-modal-replace.thml.twig';

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
            uploadData: null
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

        handleUploadData(uploadCollection) {
            if (uploadCollection.files.length > 0) {
                this.uploadData = {
                    type: 'file',
                    data: uploadCollection.files[0]
                };
                return;
            }

            if (uploadCollection.urls.length > 0) {
                this.uploadData = {
                    type: 'URL',
                    data: uploadCollection.urls[0]
                };
                return;
            }

            this.uploadData = null;
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
            debug.warn('Uploading rom Url is not supported right now');
        },

        replaceMediaFromFile() {
            const mediaId = this.itemToReplace.id;
            const mimeType = this.uploadData.data.type;

            const replaceFromFile = fileReader.readAsArrayBuffer(this.uploadData.data).then((fileAsArray) => {
                return this.mediaService.uploadMediaById(mediaId, mimeType, fileAsArray);
            });

            this.emitReplaceStarted(replaceFromFile);
        },

        emitReplaceStarted(itemUpload) {
            this.$emit('sw-media-modal-replace-replace-started', itemUpload);
        }
    }
});
