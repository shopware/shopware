import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-media-modal-replace.html.twig';

/**
 * @private
 */
Component.register('sw-media-modal-replace', {
    template,

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
            uploadTag: null
        };
    },

    computed: {
        isUploadDataSet() {
            return this.uploadTag !== null;
        },

        uploadStore() {
            return State.getStore('upload');
        },

        mediaItemStore() {
            return State.getStore('media');
        }
    },

    methods: {
        onNewUpload({ uploadTag }) {
            if (uploadTag) {
                this.uploadTag = uploadTag;
            }
        },

        emitCloseReplaceModal() {
            this.$emit('sw-media-modal-replace-close');
        },

        replaceMediaItem() {
            const notificationSuccess = this.$tc('global.sw-media-modal-replace.notificationSuccess');
            const notificationError = this.$tc(
                'global.sw-media-modal-replace.notificationFailure',
                1,
                { mediaName: this.itemToReplace.fileName }
            );

            this.itemToReplace.isLoading = true;
            this.uploadStore.runUploads(this.uploadTag).then(() => {
                this.mediaItemStore.getByIdAsync(this.itemToReplace.id).then(() => {
                    this.createNotificationSuccess({
                        message: notificationSuccess
                    });
                });
            }).catch(() => {
                this.itemToReplace.isLoading = false;
                this.createNotificationError({
                    message: notificationError
                });
            });
            this.emitCloseReplaceModal();
        }
    }
});
