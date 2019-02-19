import { Mixin, Filter } from 'src/core/shopware';
import template from './sw-media-modal-delete.html.twig';

/**
 * @status ready
 * @description The <u>sw-media-modal-delete</u> component is used to validate the delete action.
 * @example-type code-only
 * @component-example
 * <sw-media-modal-delete :itemsToDelete="[items]">
 * </sw-media-modal-delete>
 */
export default {
    name: 'sw-media-modal-delete',
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        itemsToDelete: {
            required: true,
            type: Array,
            validator(value) {
                return (value.length !== 0);
            }
        }
    },

    computed: {
        mediaNameFilter() {
            return Filter.getByName('mediaName');
        }
    },

    methods: {
        closeDeleteModal(originalDomEvent) {
            this.$emit('sw-media-modal-delete-close', { originalDomEvent });
        },

        deleteSelection() {
            const deletePromises = [];
            const notificationTitleSuccess = this.$tc('global.sw-media-modal-delete.notification.successOverall.title');
            const notificationMessageSuccess = this.$tc('global.sw-media-modal-delete.notification.successOverall.message');
            const notificationTitleError = this.$tc('global.sw-media-modal-delete.notification.errorOverall.title');
            const notificationMessageError = this.$tc('global.sw-media-modal-delete.notification.errorOverall.message');

            this.itemsToDelete.forEach((item) => {
                const messages = this._getNotificationMessages(item);
                item.isLoading = true;

                deletePromises.push(
                    item.delete(true).then(() => {
                        item.isLoading = false;
                        this.createNotificationSuccess({
                            title: messages.successTitle,
                            message: messages.successMessage
                        });
                        return item.id;
                    }).catch(() => {
                        item.isLoading = false;
                        this.createNotificationError({
                            title: messages.errorTitle,
                            message: messages.errorMessage
                        });
                    })
                );
            });

            this.$emit(
                'sw-media-modal-delete-items-deleted',
                Promise.all(deletePromises).then((ids) => {
                    this.createNotificationSuccess({
                        title: notificationTitleSuccess,
                        message: notificationMessageSuccess
                    });
                    return ids;
                }).catch(() => {
                    this.createNotificationError({
                        title: notificationTitleError,
                        message: notificationMessageError
                    });
                })
            );
        },

        _getNotificationMessages(item) {
            return {
                successMessage: this.$tc(
                    'global.sw-media-modal-delete.notification.successSingle.message',
                    1,
                    { mediaName: this.mediaNameFilter(item) }
                ),
                successTitle: this.$tc('global.sw-media-modal-delete.notification.successSingle.title'),
                errorMessage: this.$tc(
                    'global.sw-media-modal-delete.notification.errorSingle.message',
                    1,
                    { mediaName: this.mediaNameFilter(item) }
                ),
                errorTitle: this.$tc('global.sw-media-modal-delete.notification.errorSingle.title')
            };
        }
    }
};
