import { Mixin, Filter } from 'src/core/shopware';
import template from './sw-media-modal-unhide.html.twig';

/**
 * @status ready
 * @description The <u>sw-media-modal-unhide</u> component is used to validate the unhide action.
 * @example-type code-only
 * @component-example
 * <sw-media-modal-unhide :itemsToUnhide="[items]">
 * </sw-media-modal-unhide>
 */
export default {
    name: 'sw-media-modal-unhide',
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        itemsToUnhide: {
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
                    successOverall: 'global.sw-media-modal-unhide.notification.successOverall.message.mediaAndFolder',
                    errorOverall: this.$tc(
                        'global.sw-media-modal-unhide.notification.errorOverall.message.mediaAndFolder'
                    ),
                    modalTitle: this.$tc('global.sw-media-modal-unhide.titleModal.mediaAndFolder'),
                    unhideMessage: this.$tc(
                        'global.sw-media-modal-unhide.unhideMessage.mediaAndFolder',
                        this.itemsToUnhide.length,
                        {
                            mediaCount: this.mediaItems.length,
                            folderCount: this.folders.length
                        }
                    )
                };
            }

            if (this.mediaItems.length > 0) {
                return {
                    successOverall: 'global.sw-media-modal-unhide.notification.successOverall.message.media',
                    errorOverall: this.$tc('global.sw-media-modal-unhide.notification.errorOverall.message.media'),
                    modalTitle: this.$tc('global.sw-media-modal-unhide.titleModal.media'),
                    unhideMessage: this.$tc(
                        'global.sw-media-modal-unhide.unhideMessage.media',
                        this.mediaItems.length,
                        {
                            name: this.mediaNameFilter(this.mediaItems[0]),
                            count: this.mediaItems.length
                        }
                    )
                };
            }

            return {
                successOverall: 'global.sw-media-modal-unhide.notification.successOverall.message.folder',
                errorOverall: this.$tc('global.sw-media-modal-unhide.notification.errorOverall.message.folder'),
                modalTitle: this.$tc('global.sw-media-modal-unhide.titleModal.folder'),
                unhideMessage: this.$tc(
                    'global.sw-media-modal-unhide.unhideMessage.folder',
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
            this.mediaItems = this.itemsToUnhide.filter((item) => {
                return item.getEntityName() === 'media';
            });
            this.folders = this.itemsToUnhide.filter((item) => {
                return item.getEntityName() === 'media_folder';
            });
        },

        closeUnhideModal(originalDomEvent) {
            this.$emit('media-unhide-modal-close', { originalDomEvent });
        },

        unhideSelection() {
            const unhidePromises = [];

            const totalAmount = this.itemsToUnhide.length;
            let successAmount = 0;
            let failureAmount = 0;
            this.itemsToUnhide.forEach((item) => {
                item.hidden = false;
                item.isLoading = true;
                unhidePromises.push(
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
                            title: this.$root.$tc('global.sw-media-modal-unhide.notification.errorSingle.title'),
                            message: item.getEntityName() === 'media' ?
                                this.$root.$tc(
                                    'global.sw-media-modal-unhide.notification.errorSingle.message.media',
                                    1,
                                    { name: this.mediaNameFilter(item) }
                                ) :
                                this.$root.$tc(
                                    'global.sw-media-modal-unhide.notification.errorSingle.message.folder',
                                    1,
                                    { name: item.name }
                                )
                        });
                    })
                );
            });

            this.$emit(
                'media-unhide-modal-items-unhide',
                Promise.all(unhidePromises).then(() => {
                    return {
                        mediaIds: this.mediaItems.map((media) => { return media.id; }),
                        folderIds: this.folders.map((folder) => { return folder.id; })
                    };
                }).catch(() => {
                    this.createNotificationError({
                        title: this.$root.$tc('global.sw-media-modal-unhide.notification.errorOverall.title'),
                        message: this.snippets.errorOverall
                    });
                })
            );
        },

        updateSuccessNotification(successAmount, failureAmount, totalAmount) {
            const notification = {
                title: this.$root.$tc('global.sw-media-modal-unhide.notification.successOverall.title'),
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
