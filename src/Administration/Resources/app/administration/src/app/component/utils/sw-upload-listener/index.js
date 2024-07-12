import { UploadEvents } from 'src/core/service/api/media.api.service';

const { Component, Mixin, Context } = Shopware;
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

function isIllegalUrlException(error) {
    return error.response.data.errors.some((err) => {
        return err.code === 'CONTENT__MEDIA_ILLEGAL_URL';
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
 * @event media-upload-cancel UploadTask UploadTask
 * @example code-only
 * @component-example
 * <sw-upload-listener @sw-uploads-added="..."></sw-upload-listener>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-upload-listener', {
    template: '<div style="display: none"></div>',

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory', 'mediaService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        uploadTag: {
            type: String,
            required: true,
        },

        autoUpload: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            id: utils.createId(),
            notificationId: null,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
    },

    watch: {
        uploadTag(newVal, oldVal) {
            this.mediaService.removeListener(oldVal, this.convertStoreEventToVueEvent);
            this.mediaService.addListener(newVal, this.convertStoreEventToVueEvent);
        },
    },

    created() {
        this.createdComponent();
    },

    unmounted() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.mediaService.addListener(this.uploadTag, this.convertStoreEventToVueEvent);
        },

        destroyedComponent() {
            this.mediaService.removeListener(this.uploadTag, this.convertStoreEventToVueEvent);
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

            if (action === UploadEvents.UPLOAD_CANCELED) {
                this.$emit(UploadEvents.UPLOAD_CANCELED, payload);
            }
        },

        async handleError(payload) {
            this.showErrorNotification(payload);
            const updatedMedia = await this.mediaRepository.get(payload.targetId, Context.api);

            if (!updatedMedia.hasFile) {
                await this.mediaRepository.delete(updatedMedia.id, Context.api);
            }
        },

        updateSuccessNotification(uploadTag, payload) {
            const notification = {
                title: this.$root.$tc('global.default.success'),
                message: this.$root.$tc(
                    payload.customMessage ?? 'global.sw-media-upload.notification.success.message',
                    payload.successAmount,
                    {
                        count: payload.successAmount,
                        total: payload.totalAmount,
                    },
                ),
                growl: payload.successAmount + payload.failureAmount === payload.totalAmount,
            };

            if (payload.successAmount + payload.failureAmount === payload.totalAmount) {
                notification.title = this.$root.$tc('global.default.success');
            }

            if (this.notificationId !== null) {
                Shopware.State.dispatch('notification/updateNotification', {
                    uuid: this.notificationId,
                    ...notification,
                }).then(() => {
                    if (payload.successAmount + payload.failureAmount === payload.totalAmount) {
                        this.notificationId = null;
                    }
                });
                return;
            }

            Shopware.State.dispatch('notification/createNotification', {
                variant: 'success',
                ...notification,
            }).then((newNotificationId) => {
                if (payload.successAmount + payload.failureAmount < payload.totalAmount) {
                    this.notificationId = newNotificationId;
                }
            });
        },

        showErrorNotification(payload) {
            if (isIllegalFileNameException(payload.error)) {
                this.createNotificationError({
                    title: this.$root.$tc('global.default.error'),
                    message: this.$root.$tc(
                        'global.sw-media-upload.notification.illegalFilename.message',
                        0,
                        { fileName: payload.fileName },
                    ),
                });
            } else if (isIllegalUrlException(payload.error)) {
                this.createNotificationError({
                    title: this.$root.$tc('global.sw-media-upload.notification.illegalFileUrl.title'),
                    message: this.$root.$tc(
                        'global.sw-media-upload.notification.illegalFileUrl.message',
                        0,
                    ),
                });
            } else {
                this.createNotificationError({
                    title: this.$root.$tc('global.default.error'),
                    message: this.$root.$tc('global.sw-media-upload.notification.failure.message'),
                });
            }
        },

        syncEntitiesAndRunUploads() {
            this.mediaService.runUploads(this.uploadTag);
        },
    },
});
