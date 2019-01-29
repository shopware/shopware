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
            return this.targetFolder ? this.targetFolder.id : '';
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
            this.displayFolder = { id: '', name: 'Medien' };
            this.targetFolder = { id: '', name: 'Medien' };
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
            if (child.id === '') {
                this.parentFolder = null;
            } else if (child.parentId === null) {
                this.parentFolder = { id: '', name: 'Medien' };
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

            if (folder.id === '' || folder.childCount > 0) {
                this.displayFolder = folder;
            }
        },

        moveSelection() {
            const movePromises = [];
            const NotificationMessageSuccess = this.$tc('global.sw-media-modal-move.notificationSuccessOverall');
            const NotificationMessageError = this.$tc('global.sw-media-modal-move.notificationErrorOverall');

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
                            message: messages.successMessage
                        });
                        return item.id;
                    }).catch(() => {
                        item.isLoading = false;
                        this.createNotificationError({
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
                        message: NotificationMessageSuccess
                    });
                    return ids;
                }).catch(() => {
                    this.createNotificationError({
                        message: NotificationMessageError
                    });
                })
            );
        },

        _getNotificationMessages(item) {
            return {
                successMessage: this.$tc(
                    'global.sw-media-modal-move.notificationSuccessSingle',
                    1,
                    { mediaName: this.mediaNameFilter(item) }
                ),
                errorMessage: this.$tc(
                    'global.sw-media-modal-move.notificationErrorSingle',
                    1,
                    { mediaName: this.mediaNameFilter(item) }
                )
            };
        }
    }
};
