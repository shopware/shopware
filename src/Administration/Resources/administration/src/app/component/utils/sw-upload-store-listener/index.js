import { State } from 'src/core/shopware';
import { UploadEvents } from 'src/core/data/UploadStore';
import utils from 'src/core/service/util.service';

/**
 * @public
 * @description
 * component that listens to mutations of the upload store and transforms them back into the vue.js event system.
 * @status ready
 * @event sw-media-upload-added { UploadTask[]: data }
 * @event sw-media-upload-finished { string: targetId }
 * @event sw-media-upload-failed UploadTask UploadTask
 * @example code-only
 * @component-example
 * <sw-upload-store-listener @sw-uploads-added="..."></sw-upload-store-listener>
 */
export default {
    name: 'sw-upload-store-listener',

    render() {
        return document.createComment('');
    },

    props: {
        uploadTag: {
            type: String,
            required: true
        },

        autoUpload: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            id: utils.createId()
        };
    },

    computed: {
        uploadStore() {
            return State.getStore('upload');
        },

        mediaStore() {
            return State.getStore('media');
        },

        listenerKey() {
            return `sw-upload-store-listener-${this.id}`;
        }
    },

    created() {
        this.uploadStore.addListener(this.listenerKey, this.convertStoreEventToVueEvent);
    },

    destroyed() {
        this.uploadStore.removeListener(this.listenerKey);
    },

    methods: {
        convertStoreEventToVueEvent({ action, uploadTag, payload }) {
            if (this.uploadTag !== uploadTag) {
                return;
            }

            if (action === UploadEvents.UPLOAD_ADDED) {
                if (this.autoUpload === true) {
                    this.syncEntitiesAndRunUploads();
                    return;
                }

                this.$emit(UploadEvents.UPLOAD_ADDED, payload);
                return;
            }

            if (action === UploadEvents.UPLOAD_FINISHED) {
                this.$emit(UploadEvents.UPLOAD_FINISHED, payload);
                return;
            }

            if (action === UploadEvents.UPLOAD_FAILED) {
                this.$emit(UploadEvents.UPLOAD_FAILED, payload);
            }
        },

        syncEntitiesAndRunUploads() {
            this.mediaStore.sync().then(() => {
                this.uploadStore.runUploads(this.uploadTag);
            });
        }
    }
};
