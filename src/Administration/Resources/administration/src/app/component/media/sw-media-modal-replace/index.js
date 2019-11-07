import template from './sw-media-modal-replace.html.twig';
import './sw-media-modal-replace.scss';

const { Component, Mixin, StateDeprecated } = Shopware;

/**
 * @status ready
 * @description The <u>sw-media-modal-replace</u> component is used to let the user upload a new image for an
 * existing media object.
 * @example-type code-only
 * @component-example
 * <sw-media-modal-replace itemToReplace="item">
 * </sw-media-modal-replace>
 */
Component.register('sw-media-modal-replace', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    inject: ['mediaService'],

    props: {
        itemToReplace: {
            type: Object,
            required: false
        }
    },

    data() {
        return {
            uploadTag: null,
            isUploadDataSet: false,
            newFileExtension: ''
        };
    },

    computed: {
        uploadStore() {
            return StateDeprecated.getStore('upload');
        },

        mediaItemStore() {
            return StateDeprecated.getStore('media');
        }
    },

    methods: {
        onNewUpload({ data }) {
            this.isUploadDataSet = true;

            const newFileExtension = data[0].extension;
            const oldFileExtension = this.itemToReplace.fileExtension;

            if (newFileExtension !== oldFileExtension) {
                this.newFileExtension = newFileExtension;
            }
        },

        emitCloseReplaceModal() {
            this.$emit('media-replace-modal-close');
        },

        replaceMediaItem() {
            this.itemToReplace.isLoading = true;

            const previousName = this.itemToReplace.fileName;

            this.uploadStore.runUploads(this.itemToReplace.id).then(() => {
                this.mediaService.renameMedia(this.itemToReplace.id, previousName).then(() => {
                    this.mediaItemStore.getByIdAsync(this.itemToReplace.id).then(() => {
                        this.itemToReplace.isLoading = false;
                    });
                });
            }).catch(() => {
                this.itemToReplace.isLoading = false;
            });

            this.emitCloseReplaceModal();
        }
    }
});
