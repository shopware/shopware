import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-media-modal-move.html.twig';
import './sw-media-modal-move.less';

/**
 * @status ready
 * @description The <u>sw-media-modal-move</u> component is used to validate the move action.
 * @example-type code-only
 * @component-example
 * <sw-media-modal-move itemsToDelete="[items]">
 * </sw-media-modal-move>
 */
Component.register('sw-media-modal-move', {
    template,

    provide() {
        return {
            filterItems: this.isNotPartOfItemsToMove
        };
    },

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            targetFolder: null,
            parentFolder: null,
            displayFolder: null,
            displayFolderId: this.parentFolderId
        };
    },

    props: {
        itemsToMove: {
            required: true,
            type: Array,
            validator(value) {
                return (value.length > 0);
            }
        },

        parentFolderId: {
            required: false,
            type: String,
            default: null,
            validator(value) {
                return (value.length > 0);
            }
        }
    },

    watch: {
        displayFolder(newFolder) {
            this.displayFolderId = newFolder.id;
            this.updateParentFolder(newFolder);
        }
    },

    computed: {
        mediaNameFilter() {
            return (media) => { return media.name; };
        },
        mediaFolderStore() {
            return State.getStore('media_folder');
        },
        targetFolderId() {
            return this.targetFolder ? this.targetFolder.id : '';
        }
    },

    mounted() {
        this.onMountedComponent();
    },

    methods: {
        onMountedComponent() {
            if (this.parentFolderId === null) {
                this.displayFolder = { id: '', name: 'Medien' };
                this.targetFolder = { id: '', name: 'Medien' };
                return;
            }
            this.mediaFolderStore.getByIdAsync(this.parentFolderId).then((f) => {
                this.displayFolder = f;
                this.targetFolder = f;
            });
        },

        closeMoveModal() {
            this.$emit('sw-media-modal-move-close');
        },

        isNotPartOfItemsToMove(item) {
            const ids = this.itemsToMove.map((i) => { return i.id; });
            return !ids.includes(item.id);
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

        selection(folder) {
            this.targetFolder = folder;
            if (folder.id === '' || folder.childCount > 0) {
                this.displayFolder = folder;
            }
        },

        moveSelection() {
            const movePromises = [];
            const NotificationMessageSuccess = this.$tc('global.sw-media-modal-move.notificationSuccessOverall');
            const NotificationMessageError = this.$tc('global.sw-media-modal-move.notificationErrorOverall');

            this.itemsToMove.forEach((item) => {
                const messages = this._getNotificationMessages(item);
                item.isLoading = true;

                movePromises.push(
                    // TODO implement Move
                    Promise.resolve().then(() => {
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
});
