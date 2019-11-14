import template from './sw-media-modal-move.html.twig';
import './sw-media-modal-move.scss';

const { Component, Mixin, StateDeprecated } = Shopware;

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
                return media.getEntityName() === 'media' ?
                    `${media.fileName}.${media.fileExtension}` :
                    media.name;
            };
        },

        mediaFolderStore() {
            return StateDeprecated.getStore('media_folder');
        },

        mediaStore() {
            return StateDeprecated.getStore('media');
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
            if (firstItem.getEntityName() === 'media') {
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
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
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
            this.$emit('media-move-modal-close');
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

            this.itemsToMove.filter((item) => {
                return item.getEntityName() === 'media_folder';
            }).forEach((item) => {
                item.isLoading = true;
                item.parentId = this.targetFolder.id || null;
                movePromises.push(
                    item.save().then(() => {
                        item.isLoading = false;
                        this.createNotificationSuccess({
                            title: this.$root.$tc('global.default.success'),
                            message: this.$root.$tc(
                                'global.sw-media-modal-move.notification.successSingle.message',
                                1,
                                { mediaName: this.mediaNameFilter(item) }
                            )
                        });
                        return item.id;
                    }).catch(() => {
                        item.isLoading = false;
                        this.createNotificationError({
                            title: this.$root.$tc('global.default.error'),
                            message: this.$root.$tc(
                                'global.sw-media-modal-move.notification.errorSingle.message',
                                1,
                                { mediaName: this.mediaNameFilter(item) }
                            )
                        });
                    })
                );
            });

            this.itemsToMove.filter((item) => {
                return item.getEntityName() === 'media';
            }).forEach((item) => {
                item.mediaFolderId = this.targetFolder.id || null;
            });
            movePromises.push(this.mediaStore.sync());

            this.$emit(
                'media-move-modal-items-move',
                Promise.all(movePromises).then((ids) => {
                    this.createNotificationSuccess({
                        title: this.$root.$tc('global.default.success'),
                        message: this.$root.$tc('global.sw-media-modal-move.notification.successOverall.message')
                    });
                    return ids;
                }).catch(() => {
                    this.createNotificationError({
                        title: this.$root.$tc('global.default.error'),
                        message: this.$root.$tc('global.sw-media-modal-move.notification.errorOverall.message')
                    });
                })
            );
        }
    }
});
