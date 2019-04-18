import { Mixin, State } from 'src/core/shopware';
import { UploadEvents } from 'src/core/data/UploadStore';
import utils from 'src/core/service/util.service';

/**
 * @private
 */

function isIllegalFileNameException(error) {
    return error.response.data.errors.some((err) => {
        return err.code === 'ILLEGAL_FILE_NAME_EXCEPTION';
    });
}

function isDuplicationException(error) {
    return error.response.data.errors.some((err) => {
        return err.code === 'CONTENT__MEDIA_DUPLICATED_FILE_NAME';
    });
}

/**
 * @public
 * @description
 * component that listens to mutations of the upload store and transforms them back into the vue.js event system.
 * @status ready
 * @event sw-media-upload-added { UploadTask[]: data }
 * @event sw-media-upload-finished { string: targetId }
 * @event media-upload-fail UploadTask UploadTask
 * @example code-only
 * @component-example
 * <sw-upload-store-listener @sw-uploads-added="..."></sw-upload-store-listener>
 */
export default {
    name: 'sw-upload-store-listener',

    render() {
        return document.createComment('');
    },

    mixins: [
        Mixin.getByName('notification')
    ],

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
        }
    },

    watch: {
        uploadTag(newVal, oldVal) {
            this.uploadStore.removeListener(oldVal, this.convertStoreEventToVueEvent);
            this.uploadStore.addListener(newVal, this.convertStoreEventToVueEvent);
        }
    },

    created() {
        this.uploadStore.addListener(this.uploadTag, this.convertStoreEventToVueEvent);
    },

    destroyed() {
        this.uploadStore.removeListener(this.uploadTag, this.convertStoreEventToVueEvent);
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
                this.createNotificationSuccess({
                    title: this.$root.$tc('global.sw-media-upload.notification.success.title'),
                    message: this.$root.$tc('global.sw-media-upload.notification.success.message')
                });
                this.$emit(UploadEvents.UPLOAD_FINISHED, payload);
                return;
            }

            if (action === UploadEvents.UPLOAD_FAILED) {
                if (isDuplicationException(payload.error)) {
                    this.$emit(UploadEvents.UPLOAD_FAILED, payload);
                    return;
                }

                this.handleError(payload).then(() => {
                    this.$emit(UploadEvents.UPLOAD_FAILED, payload);
                });
            }
        },

        handleError(payload) {
            this.showErrorNotification(payload);
            return this.mediaStore.getByIdAsync(payload.targetId).then((updatedMedia) => {
                if (!updatedMedia.hasFile) {
                    updatedMedia.delete(true);
                }
            });
        },

        showErrorNotification(payload) {
            if (isIllegalFileNameException(payload.error)) {
                this.createNotificationError({
                    title: this.$root.$tc('global.sw-media-upload.notification.illegalFilename.title'),
                    message: this.$root.$tc(
                        'global.sw-media-upload.notification.illegalFilename.message',
                        0,
                        { fileName: payload.fileName }
                    )
                });
            } else {
                this.createNotificationError({
                    title: this.$root.$tc('global.sw-media-upload.notification.failure.title'),
                    message: this.$root.$tc('global.sw-media-upload.notification.failure.message')
                });
            }
        },

        syncEntitiesAndRunUploads() {
            this.mediaStore.sync().then(() => {
                this.uploadStore.runUploads(this.uploadTag);
            });
        }
    }
};
