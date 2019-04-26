import { Mixin, State } from 'src/core/shopware';
import template from './sw-media-modal-replace.html.twig';

/**
 * @status ready
 * @description The <u>sw-media-modal-replace</u> component is used to let the user upload a new image for an
 * existing media object.
 * @example-type code-only
 * @component-example
 * <sw-media-modal-replace itemToReplace="item">
 * </sw-media-modal-replace>
 */
export default {
    name: 'sw-media-modal-replace',
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
            uploadTag: null,
            isUploadDataSet: false
        };
    },

    computed: {
        uploadStore() {
            return State.getStore('upload');
        },

        mediaItemStore() {
            return State.getStore('media');
        }
    },

    methods: {
        onNewUpload() {
            this.isUploadDataSet = true;
        },

        emitCloseReplaceModal() {
            this.$emit('media-replace-modal-close');
        },

        replaceMediaItem() {
            this.itemToReplace.isLoading = true;
            this.uploadStore.runUploads(this.itemToReplace.id).then(() => {
                this.mediaItemStore.getByIdAsync(this.itemToReplace.id).then(() => {
                });
            }).catch(() => {
                this.itemToReplace.isLoading = false;
            });
            this.emitCloseReplaceModal();
        }
    }
};
