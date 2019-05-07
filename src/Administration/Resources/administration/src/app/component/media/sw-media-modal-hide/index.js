import { Mixin, Filter } from 'src/core/shopware';
import template from './sw-media-modal-hide.html.twig';

/**
 * @status ready
 * @description The <u>sw-media-modal-hide</u> component is used to validate the hide action.
 * @example-type code-only
 * @component-example
 * <sw-media-modal-hide :itemsToHide="[items]">
 * </sw-media-modal-hide>
 */
export default {
    name: 'sw-media-modal-hide',
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        itemsToHide: {
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
                    successOverall: 'global.sw-media-modal-hide.notification.successOverall.message.mediaAndFolder',
                    errorOverall: this.$tc(
                        'global.sw-media-modal-hide.notification.errorOverall.message.mediaAndFolder'
                    ),
                    modalTitle: this.$tc('global.sw-media-modal-hide.titleModal.mediaAndFolder'),
                    hideMessage: this.$tc(
                        'global.sw-media-modal-hide.hideMessage.mediaAndFolder',
                        this.itemsToHide.length,
                        {
                            mediaCount: this.mediaItems.length,
                            folderCount: this.folders.length
                        }
                    )
                };
            }

            if (this.mediaItems.length > 0) {
                return {
                    successOverall: 'global.sw-media-modal-hide.notification.successOverall.message.media',
                    errorOverall: this.$tc('global.sw-media-modal-hide.notification.errorOverall.message.media'),
                    modalTitle: this.$tc('global.sw-media-modal-hide.titleModal.media'),
                    hideMessage: this.$tc(
                        'global.sw-media-modal-hide.hideMessage.media',
                        this.mediaItems.length,
                        {
                            name: this.mediaNameFilter(this.mediaItems[0]),
                            count: this.mediaItems.length
                        }
                    )
                };
            }

            return {
                successOverall: 'global.sw-media-modal-hide.notification.successOverall.message.folder',
                errorOverall: this.$tc('global.sw-media-modal-hide.notification.errorOverall.message.folder'),
                modalTitle: this.$tc('global.sw-media-modal-hide.titleModal.folder'),
                hideMessage: this.$tc(
                    'global.sw-media-modal-hide.hideMessage.folder',
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
        this.componentCreated();
    },

    methods: {
        componentCreated() {
            this.mediaItems = this.itemsToHide.filter((item) => {
                return item.getEntityName() === 'media';
            });
            this.folders = this.itemsToHide.filter((item) => {
                return item.getEntityName() === 'media_folder';
            });
        },

        closeHideModal(originalDomEvent) {
            this.$emit('media-hide-modal-close', { originalDomEvent });
        },

        hideSelection() {
            const hidePromises = [];

            const totalAmount = this.itemsToHide.length;
            let successAmount = 0;
            let failureAmount = 0;
            this.itemsToHide.forEach((item) => {
                item.hidden = true;
                item.isLoading = true;
                hidePromises.push(
                    item.save().then(() => {
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
                            title: this.$root.$tc('global.sw-media-modal-hide.notification.errorSingle.title'),
                            message: item.getEntityName() === 'media' ?
                                this.$root.$tc(
                                    'global.sw-media-modal-hide.notification.errorSingle.message.media',
                                    1,
                                    { name: this.mediaNameFilter(item) }
                                ) :
                                this.$root.$tc(
                                    'global.sw-media-modal-hide.notification.errorSingle.message.folder',
                                    1,
                                    { name: item.name }
                                )
                        });
                    })
                );
            });

            this.$emit(
                'media-hide-modal-items-hide',
                Promise.all(hidePromises).then(() => {
                    return {
                        mediaIds: this.mediaItems.map((media) => { return media.id; }),
                        folderIds: this.folders.map((folder) => { return folder.id; })
                    };
                }).catch(() => {
                    this.createNotificationError({
                        title: this.$root.$tc('global.sw-media-modal-hide.notification.errorOverall.title'),
                        message: this.snippets.errorOverall
                    });
                })
            );
        },

        updateSuccessNotification(successAmount, failureAmount, totalAmount) {
            const notification = {
                title: this.$root.$tc('global.sw-media-modal-hide.notification.successOverall.title'),
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
                this.$store.dispatch('notification/updateNotification', {
                    uuid: this.notificationId,
                    ...notification
                }).then(() => {
                    if (successAmount + failureAmount === totalAmount) {
                        this.notificationId = null;
                    }
                });
                return;
            }

            this.$store.dispatch('notification/createNotification', {
                variant: 'success',
                ...notification
            }).then((newNotificationId) => {
                if (successAmount + failureAmount < totalAmount) {
                    this.notificationId = newNotificationId;
                }
            });
        }
    }
};
