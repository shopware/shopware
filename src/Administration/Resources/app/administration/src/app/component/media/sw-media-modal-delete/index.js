import template from './sw-media-modal-delete.html.twig';
import './sw-media-modal-delete.scss';

const { Component, Context, Mixin, Filter } = Shopware;

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

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        itemsToDelete: {
            required: true,
            type: Array,
            validator(value) {
                return (value.length !== 0);
            },
        },
    },

    data() {
        return {
            mediaItems: [],
            folders: [],
            notificationId: null,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },
        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },
        mediaNameFilter() {
            return Filter.getByName('mediaName');
        },

        snippets() {
            if (this.mediaItems.length > 0 && this.folders.length > 0) {
                return {
                    successOverall: 'global.sw-media-modal-delete.notification.successOverall.message.mediaAndFolder',
                    errorOverall: this.$tc(
                        'global.sw-media-modal-delete.notification.errorOverall.message.mediaAndFolder',
                    ),
                    modalTitle: this.$tc('global.default.warning'),
                    deleteMessage: this.$tc(
                        'global.sw-media-modal-delete.deleteMessage.mediaAndFolder',
                        this.itemsToDelete.length,
                        {
                            mediaCount: this.mediaItems.length,
                            folderCount: this.folders.length,
                        },
                    ),
                };
            }

            if (this.mediaItems.length > 0) {
                return {
                    successOverall: 'global.sw-media-modal-delete.notification.successOverall.message.media',
                    errorOverall: this.$tc('global.sw-media-modal-delete.notification.errorOverall.message.media'),
                    modalTitle: this.$tc('global.default.warning'),
                    deleteMessage: this.$tc(
                        'global.sw-media-modal-delete.deleteMessage.media',
                        this.mediaItems.length,
                        {
                            name: this.mediaNameFilter(this.mediaItems[0]),
                            count: this.mediaItems.length,
                        },
                    ),
                };
            }

            return {
                successOverall: 'global.sw-media-modal-delete.notification.successOverall.message.folder',
                errorOverall: this.$tc('global.sw-media-modal-delete.notification.errorOverall.message.folder'),
                modalTitle: this.$tc('global.default.warning'),
                deleteMessage: this.$tc(
                    'global.sw-media-modal-delete.deleteMessage.folder',
                    this.folders.length,
                    {
                        name: this.folders[0].name,
                        count: this.folders.length,
                    },
                ),
            };
        },

        mediaQuickInfo() {
            const usedMediaItem = this.mediaItems.length === 1 && this._checkInUsage(this.mediaItems[0]);
            return usedMediaItem ? this.mediaItems[0] : null;
        },

        mediaInUsages() {
            if (this.mediaItems.length <= 1) return [];

            return this.mediaItems.filter(mediaItem => this._checkInUsage(mediaItem));
        },
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

        getEntityRepository(entityName) {
            if (entityName === 'media') {
                return this.mediaRepository;
            }

            if (entityName === 'media_folder') {
                return this.mediaFolderRepository;
            }

            return null;
        },

        _deleteSelection(item) {
            const entityName = item.getEntityName();
            const repository = this.getEntityRepository(entityName);

            item.isLoading = true;

            return repository.delete(item.id, Context.api)
                .then(() => {
                    return true;
                })
                .catch(() => {
                    const isMedia = item.getEntityName() === 'media';
                    const errorSnippet = 'global.sw-media-modal-delete.notification.errorSingle.message';

                    const message = isMedia ?
                        this.$tc(`${errorSnippet}.media`, 1, { name: this.mediaNameFilter(item) }) :
                        this.$tc(`${errorSnippet}.folder`, 1, { name: item.name });

                    this.createNotificationError({
                        message,
                    });

                    return false;
                })
                .finally(() => {
                    item.isLoading = false;
                });
        },

        async deleteSelection() {
            const deleteSelections = this.itemsToDelete.map((item) => {
                return this._deleteSelection(item).catch(() => false);
            });

            const deletions = await Promise.all(deleteSelections);

            const amounts = deletions.reduce((acc, isSuccess) => {
                acc.success = isSuccess ? acc.success += 1 : acc.success;
                acc.failure = isSuccess ? acc.failure : acc.failure += 1;

                return acc;
            }, { success: 0, failure: 0 });

            if (amounts.success > 0) {
                this.updateSuccessNotification(amounts.success, amounts.failure, deletions.length);
            }

            this.$emit(
                'media-delete-modal-items-delete',
                {
                    mediaIds: this.mediaItems.map((media) => { return media.id; }),
                    folderIds: this.folders.map((folder) => { return folder.id; }),
                },
            );
        },

        async updateSuccessNotification(successAmount, failureAmount, totalAmount) {
            const notification = {
                message: this.$tc(
                    this.snippets.successOverall,
                    successAmount,
                    {
                        count: successAmount,
                        total: totalAmount,
                    },
                ),
                growl: successAmount + failureAmount === totalAmount,
            };

            if (this.notificationId !== null) {
                await Shopware.State.dispatch('notification/updateNotification', {
                    uuid: this.notificationId,
                    ...notification,
                });

                if (successAmount + failureAmount === totalAmount) {
                    this.notificationId = null;
                }

                return;
            }

            const newNotificationId = await Shopware.State.dispatch('notification/createNotification', {
                variant: 'success',
                ...notification,
            });

            if (successAmount + failureAmount < totalAmount) {
                this.notificationId = newNotificationId;
            }
        },

        _checkInUsage(mediaItem) {
            if (mediaItem.avatarUser) {
                return true;
            }

            const mediaAssociations = [
                'categories',
                'productMedia',
                'productManufacturers',
                'mailTemplateMedia',
                'documentBaseConfigs',
                'paymentMethods',
                'shippingMethods',
            ];

            return mediaAssociations.some((association) => {
                return mediaItem[association].length > 0;
            });
        },
    },
});
