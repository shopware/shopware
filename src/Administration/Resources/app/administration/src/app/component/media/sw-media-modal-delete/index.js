import template from './sw-media-modal-delete.html.twig';

const { Component, Mixin, Filter } = Shopware;

/**
 * @status ready
 * @description The <u>sw-media-modal-delete</u> component is used to validate the delete action.
 * @example-type code-only
 * @component-example
 * <sw-media-modal-delete :itemsToDelete="[items]">
 * </sw-media-modal-delete>
 */
Component.register('sw-media-modal-delete', {
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

    data() {
        return {
            mediaItems: [],
            folders: [],
            notificationId: null
        };
    },

    computed: {
        mediaNameFilter() {
            return Filter.getByName('mediaName');
        },

        snippets() {
            if (this.mediaItems.length > 0 && this.folders.length > 0) {
                return {
                    successOverall: 'global.sw-media-modal-delete.notification.successOverall.message.mediaAndFolder',
                    errorOverall: this.$tc(
                        'global.sw-media-modal-delete.notification.errorOverall.message.mediaAndFolder'
                    ),
                    modalTitle: this.$tc('global.sw-media-modal-delete.titleModal.mediaAndFolder'),
                    deleteMessage: this.$tc(
                        'global.sw-media-modal-delete.deleteMessage.mediaAndFolder',
                        this.itemsToDelete.length,
                        {
                            mediaCount: this.mediaItems.length,
                            folderCount: this.folders.length
                        }
                    )
                };
            }

            if (this.mediaItems.length > 0) {
                return {
                    successOverall: 'global.sw-media-modal-delete.notification.successOverall.message.media',
                    errorOverall: this.$tc('global.sw-media-modal-delete.notification.errorOverall.message.media'),
                    modalTitle: this.$tc('global.sw-media-modal-delete.titleModal.media'),
                    deleteMessage: this.$tc(
                        'global.sw-media-modal-delete.deleteMessage.media',
                        this.mediaItems.length,
                        {
                            name: this.mediaNameFilter(this.mediaItems[0]),
                            count: this.mediaItems.length
                        }
                    )
                };
            }

            return {
                successOverall: 'global.sw-media-modal-delete.notification.successOverall.message.folder',
                errorOverall: this.$tc('global.sw-media-modal-delete.notification.errorOverall.message.folder'),
                modalTitle: this.$tc('global.sw-media-modal-delete.titleModal.folder'),
                deleteMessage: this.$tc(
                    'global.sw-media-modal-delete.deleteMessage.folder',
                    this.folders.length,
                    {
                        name: this.folders[0].name,
                        count: this.folders.length
                    }
                )
            };
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.mediaItems = this.itemsToDelete.filter((item) => {
                return item.getEntityName() === 'media';
            });
            this.folders = this.itemsToDelete.filter((item) => {
                return item.getEntityName() === 'media_folder';
            });
        },

        closeDeleteModal(originalDomEvent) {
            this.$emit('media-delete-modal-close', { originalDomEvent });
        },

        deleteSelection() {
            const deletePromises = [];

            const totalAmount = this.itemsToDelete.length;
            let successAmount = 0;
            let failureAmount = 0;
            this.itemsToDelete.forEach((item) => {
                item.isLoading = true;

                deletePromises.push(
                    item.delete(true).then(() => {
                        item.isLoading = false;
                        successAmount += 1;
                        this.updateSuccessNotification(successAmount, failureAmount, totalAmount);
                    }).catch(() => {
                        item.isLoading = false;
                        failureAmount += 1;
                        if (successAmount + failureAmount === totalAmount &&
                            totalAmount !== failureAmount) {
                            this.updateSuccessNotification(successAmount, failureAmount, totalAmount);
                        }

                        this.createNotificationError({
                            title: this.$root.$tc('global.default.error'),
                            message: item.getEntityName() === 'media' ?
                                this.$root.$tc(
                                    'global.sw-media-modal-delete.notification.errorSingle.message.media',
                                    1,
                                    { name: this.mediaNameFilter(item) }
                                ) :
                                this.$root.$tc(
                                    'global.sw-media-modal-delete.notification.errorSingle.message.folder',
                                    1,
                                    { name: item.name }
                                )
                        });
                    })
                );
            });

            this.$emit(
                'media-delete-modal-items-delete',
                Promise.all(deletePromises).then(() => {
                    return {
                        mediaIds: this.mediaItems.map((media) => { return media.id; }),
                        folderIds: this.folders.map((folder) => { return folder.id; })
                    };
                }).catch(() => {
                    this.createNotificationError({
                        title: this.$root.$tc('global.default.error'),
                        message: this.snippets.errorOverall
                    });
                })
            );
        },

        updateSuccessNotification(successAmount, failureAmount, totalAmount) {
            const notification = {
                title: this.$root.$tc('global.default.success'),
                message: this.$root.$tc(
                    this.snippets.successOverall,
                    successAmount,
                    {
                        count: successAmount,
                        total: totalAmount
                    }
                ),
                growl: successAmount + failureAmount === totalAmount
            };

            if (this.notificationId !== null) {
                Shopware.State.dispatch('notification/updateNotification', {
                    uuid: this.notificationId,
                    ...notification
                }).then(() => {
                    if (successAmount + failureAmount === totalAmount) {
                        this.notificationId = null;
                    }
                });
                return;
            }

            Shopware.State.dispatch('notification/createNotification', {
                variant: 'success',
                ...notification
            }).then((newNotificationId) => {
                if (successAmount + failureAmount < totalAmount) {
                    this.notificationId = newNotificationId;
                }
            });
        }
    }
});
