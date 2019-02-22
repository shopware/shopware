import { Mixin, State } from 'src/core/shopware';
import template from './sw-media-modal-move.html.twig';
import './sw-media-modal-move.scss';

/**
 * @status ready
 * @description The <u>sw-media-modal-move</u> component is used to validate the move action.
 * @example-type code-only
 * @component-example
 * <sw-media-modal-move itemsToDelete="[items]">
 * </sw-media-modal-move>
 */
export default {
    name: 'sw-media-modal-move',
    template,

    inject: ['mediaFolderService'],

    provide() {
        return {
            filterItems: this.isNotPartOfItemsToMove
        };
    },

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        itemsToMove: {
            required: true,
            type: Array,
            validator(value) {
                return (value.length > 0);
            }
        }
    },

    data() {
        return {
            targetFolder: null,
            parentFolder: null,
            displayFolder: null,
            displayFolderId: null
        };
    },

    computed: {
        mediaNameFilter() {
            return (media) => {
                return media.entityName === 'media' ?
                    `${media.fileName}.${media.fileExtension}` :
                    media.name;
            };
        },

        mediaFolderStore() {
            return State.getStore('media_folder');
        },

        mediaStore() {
            return State.getStore('media');
        },

        targetFolderId() {
            return this.targetFolder ? this.targetFolder.id : null;
        },

        rootFolderName() {
            return this.$tc('sw-media.index.rootFolderName');
        },

        isMoveDisabled() {
            return this.startFolderId === this.targetFolderId;
        },

        startFolderId() {
            const firstItem = this.itemsToMove[0];
            if (firstItem.entityName === 'media') {
                return firstItem.mediaFolderId;
            }

            return firstItem.parentId;
        }
    },

    watch: {
        displayFolder(newFolder) {
            this.displayFolderId = newFolder.id;
            this.updateParentFolder(newFolder);
        }
    },

    mounted() {
        this.onMountedComponent();
    },

    methods: {
        onMountedComponent() {
            this.displayFolder = { id: null, name: this.rootFolderName };
            this.targetFolder = { id: null, name: this.rootFolderName };

            if (this.startFolderId) {
                this.mediaFolderStore.getByIdAsync(this.startFolderId).then((folder) => {
                    this.displayFolder = folder;
                    this.targetFolder = folder;
                });
            }
        },

        closeMoveModal() {
            this.$emit('sw-media-modal-move-close');
        },

        isNotPartOfItemsToMove(item) {
            return !this.itemsToMove.some((i) => {
                return i.id === item.id;
            });
        },

        updateParentFolder(child) {
            if (child.id === null) {
                this.parentFolder = null;
            } else if (child.parentId === null) {
                this.parentFolder = { id: null, name: this.rootFolderName };
            } else {
                this.mediaFolderStore.getByIdAsync(child.parentId).then((parent) => {
                    this.parentFolder = parent;
                });
            }
        },

        onSelection(folder) {
            this.targetFolder = folder;
            // the children aren't always loaded
            if (folder.children) {
                if (folder.children.filter(this.isNotPartOfItemsToMove).length > 0) {
                    this.displayFolder = folder;
                }
                return;
            }

            if (folder.id === null || folder.childCount > 0) {
                this.displayFolder = folder;
            }
        },

        moveSelection() {
            const movePromises = [];
            const NotificationTitleSuccess = this.$tc('global.sw-media-modal-move.notification.successOverall.title');
            const NotificationMessageSuccess = this.$tc('global.sw-media-modal-move.notification.successOverall.message');
            const NotificationTitleError = this.$tc('global.sw-media-modal-move.notification.errorOverall.title');
            const NotificationMessageError = this.$tc('global.sw-media-modal-move.notification.errorOverall.message');

            this.itemsToMove.filter((item) => {
                return item.entityName === 'media_folder';
            }).forEach((item) => {
                const messages = this._getNotificationMessages(item);
                item.isLoading = true;
                item.parentId = this.targetFolder.id || null;
                movePromises.push(
                    item.save().then(() => {
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

            this.itemsToMove.filter((item) => {
                return item.entityName === 'media';
            }).forEach((item) => {
                item.mediaFolderId = this.targetFolder.id || null;
            });
            movePromises.push(this.mediaStore.sync());

            this.$emit(
                'sw-media-modal-move-items-moved',
                Promise.all(movePromises).then((ids) => {
                    this.createNotificationSuccess({
                        title: NotificationTitleSuccess,
                        message: NotificationMessageSuccess
                    });
                    return ids;
                }).catch(() => {
                    this.createNotificationError({
                        title: NotificationTitleError,
                        message: NotificationMessageError
                    });
                })
            );
        },

        _getNotificationMessages(item) {
            return {
                successTitle: this.$tc('global.sw-media-modal-move.notification.successSingle.title'),
                successMessage: this.$tc(
                    'global.sw-media-modal-move.notification.successSingle.message',
                    1,
                    { mediaName: this.mediaNameFilter(item) }
                ),
                errorTitle: this.$tc('global.sw-media-modal-move.notification.errorSingle.title'),
                errorMessage: this.$tc(
                    'global.sw-media-modal-move.notification.errorSingle.message',
                    1,
                    { mediaName: this.mediaNameFilter(item) }
                )
            };
        }
    }
};
