import template from './sw-media-modal-delete.html.twig';
import './sw-media-modal-delete.scss';
import { useSnackbar } from '@shopware-ag/meteor-component-library';

const { Context, Mixin, Filter } = Shopware;
const MAX_NOTIFICATION_NAME_LENGTH = 48;

/**
 * @status ready
 * @description The <u>sw-media-modal-delete</u> component is used to validate the delete action.
 * @sw-package discovery
 * @example-type code-only
 * @component-example
 * <sw-media-modal-delete :itemsToDelete="[items]">
 * </sw-media-modal-delete>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    emits: [
        'media-delete-modal-close',
        'media-delete-modal-items-delete',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        itemsToDelete: {
            required: true,
            type: Array,
            validator(value) {
                return value.length !== 0;
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

        snackbar() {
            return useSnackbar();
        },

        snippets() {
            if (this.mediaItems.length > 0 && this.folders.length > 0) {
                return {
                    successOverall: 'global.sw-media-modal-delete.notification.successOverall.message.mediaAndFolder',
                    errorOverall: this.$t('global.sw-media-modal-delete.notification.errorOverall.message.mediaAndFolder'),
                    modalTitle: this.$t('global.default.warning'),
                    deleteMessage: this.$t(
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
                    errorOverall: this.$t('global.sw-media-modal-delete.notification.errorOverall.message.media'),
                    modalTitle: this.$t('global.default.warning'),
                    deleteMessage: this.$t(
                        'global.sw-media-modal-delete.deleteMessage.media',
                        {
                            name: this.mediaNameFilter(this.mediaItems[0]),
                            count: this.mediaItems.length,
                        },
                        this.mediaItems.length,
                    ),
                };
            }

            return {
                successOverall: 'global.sw-media-modal-delete.notification.successOverall.message.folder',
                errorOverall: this.$t('global.sw-media-modal-delete.notification.errorOverall.message.folder'),
                modalTitle: this.$t('global.default.warning'),
                deleteMessage: this.$t(
                    'global.sw-media-modal-delete.deleteMessage.folder',
                    {
                        name: this.folders[0].name,
                        count: this.folders.length,
                    },
                    this.folders.length,
                ),
            };
        },

        mediaQuickInfo() {
            const usedMediaItem = this.mediaItems.length === 1 && this._checkInUsage(this.mediaItems[0]);
            return usedMediaItem ? this.mediaItems[0] : null;
        },

        mediaInUsages() {
            if (this.mediaItems.length <= 1) return [];

            return this.mediaItems.filter((mediaItem) => this._checkInUsage(mediaItem));
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

            return repository
                .delete(item.id, Context.api)
                .then(() => {
                    return {
                        item,
                        success: true,
                    };
                })
                .catch(() => {
                    const isMedia = item.getEntityName() === 'media';
                    const errorSnippet = 'global.sw-media-modal-delete.notification.errorSingle.message';
                    const name = this.getDeleteItemName(item);

                    const message = isMedia
                        ? this.$t(
                              `${errorSnippet}.media`,
                              {
                                  name,
                              },
                              1,
                          )
                        : this.$t(
                              `${errorSnippet}.folder`,
                              {
                                  name,
                              },
                              1,
                          );

                    this.createNotificationError({
                        message,
                    });

                    return {
                        item,
                        success: false,
                    };
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

            const amounts = deletions.reduce(
                (acc, deletion) => {
                    acc.success = deletion.success ? (acc.success += 1) : acc.success;
                    acc.failure = deletion.success ? acc.failure : (acc.failure += 1);

                    return acc;
                },
                { success: 0, failure: 0 },
            );

            if (amounts.success > 0) {
                this.updateSuccessNotification(amounts.success, amounts.failure, deletions.length);
            }

            this.$emit('media-delete-modal-items-delete', {
                mediaIds: this.getSuccessfulDeletedItems(deletions, 'media').map((media) => {
                    return media.id;
                }),
                folderIds: this.getSuccessfulDeletedItems(deletions, 'media_folder').map((folder) => {
                    return folder.id;
                }),
            });
        },

        getSuccessfulDeletedItems(deletions, entityName) {
            return deletions
                .filter((deletion) => {
                    return deletion.success && deletion.item.getEntityName() === entityName;
                })
                .map((deletion) => deletion.item);
        },

        getDeleteItemName(item) {
            const isMedia = item.getEntityName() === 'media';
            const fallback = isMedia
                ? item.fileName ||
                  item.name ||
                  this.$t('global.sw-media-modal-delete.notification.errorSingle.fallback.media')
                : item.name || this.$t('global.sw-media-modal-delete.notification.errorSingle.fallback.folder');
            const name = isMedia ? this.mediaNameFilter(item, fallback) : fallback;

            return this.truncateMiddle(name);
        },

        truncateMiddle(value, maxLength = MAX_NOTIFICATION_NAME_LENGTH) {
            if (value.length <= maxLength) {
                return value;
            }

            const ellipsis = '...';
            const availableLength = maxLength - ellipsis.length;
            const startLength = Math.ceil(availableLength / 2);
            const endLength = Math.floor(availableLength / 2);

            return `${value.slice(0, startLength)}${ellipsis}${value.slice(-endLength)}`;
        },

        updateSuccessNotification(successAmount, failureAmount, totalAmount) {
            const snackbar = {
                variant: 'success',
                message: this.$t(
                    this.snippets.successOverall,
                    {
                        count: successAmount,
                        total: totalAmount,
                    },
                    successAmount,
                ),
            };

            this.snackbar.addSnackbar(snackbar);
        },

        _checkInUsage(mediaItem) {
            if (mediaItem.avatarUsers?.[0]) {
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
                'cmsBlocks',
                'cmsSections',
                'cmsPages',
            ];

            return mediaAssociations.some((association) => {
                return (mediaItem[association] ?? []).length > 0;
            });
        },
    },
};
