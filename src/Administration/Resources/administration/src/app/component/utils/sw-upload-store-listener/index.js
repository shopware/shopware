import { UploadEvents } from 'src/core/data/UploadStore';

const { Component, Mixin, StateDeprecated } = Shopware;
const utils = Shopware.Utils;

/**
 * @private
 */

function isIllegalFileNameException(error) {
    return error.response.data.errors.some((err) => {
        return err.code === 'CONTENT__MEDIA_ILLEGAL_FILE_NAME';
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
 * @event media-upload-add { UploadTask[]: data }
 * @event media-upload-finish { string: targetId }
 * @event media-upload-fail UploadTask UploadTask
 * @example code-only
 * @component-example
 * <sw-upload-store-listener @sw-uploads-added="..."></sw-upload-store-listener>
 */
Component.register('sw-upload-store-listener', {
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
            id: utils.createId(),
            notificationId: null
        };
    },

    computed: {
        uploadStore() {
            return StateDeprecated.getStore('upload');
        },

        mediaStore() {
            return StateDeprecated.getStore('media');
        }
    },

    watch: {
        uploadTag(newVal, oldVal) {
            this.uploadStore.removeListener(oldVal, this.convertStoreEventToVueEvent);
            this.uploadStore.addListener(newVal, this.convertStoreEventToVueEvent);
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.uploadStore.addListener(this.uploadTag, this.convertStoreEventToVueEvent);
        },

        destroyedComponent() {
            this.uploadStore.removeListener(this.uploadTag, this.convertStoreEventToVueEvent);
        },

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
                this.updateSuccessNotification(uploadTag, payload);
                this.$emit(UploadEvents.UPLOAD_FINISHED, payload);
                return;
            }

            if (action === UploadEvents.UPLOAD_FAILED) {
                if (payload.successAmount + payload.failureAmount === payload.totalAmount &&
                    payload.totalAmount !== payload.failureAmount) {
                    this.updateSuccessNotification(uploadTag, payload);
                }
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

        updateSuccessNotification(uploadTag, payload) {
            const notification = {
                title: this.$root.$tc('global.default.success'),
                message: this.$root.$tc(
                    'global.sw-media-upload.notification.success.message',
                    payload.successAmount,
                    {
                        count: payload.successAmount,
                        total: payload.totalAmount
                    }
                ),
                growl: payload.successAmount + payload.failureAmount === payload.totalAmount
            };

            if (payload.successAmount + payload.failureAmount === payload.totalAmount) {
                notification.title = this.$root.$tc('global.default.success');
            }

            if (this.notificationId !== null) {
                Shopware.State.dispatch('notification/updateNotification', {
                    uuid: this.notificationId,
                    ...notification
                }).then(() => {
                    if (payload.successAmount + payload.failureAmount === payload.totalAmount) {
                        this.notificationId = null;
                    }
                });
                return;
            }

            Shopware.State.dispatch('notification/createNotification', {
                variant: 'success',
                ...notification
            }).then((newNotificationId) => {
                if (payload.successAmount + payload.failureAmount < payload.totalAmount) {
                    this.notificationId = newNotificationId;
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
                    title: this.$root.$tc('global.default.error'),
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
});
