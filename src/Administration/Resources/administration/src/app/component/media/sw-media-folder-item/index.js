import template from './sw-media-folder-item.html.twig';
import './sw-media-folder-item.scss';

const { Component, Application, Mixin, StateDeprecated } = Shopware;

Component.register('sw-media-folder-item', {
    template,
    inheritAttrs: false,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        isParent: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            showSettings: false,
            showDissolveModal: false,
            showMoveModal: false,
            showDeleteModal: false,
            lastDefaultFolderId: null,
            iconConfig: {
                name: '',
                color: 'inherit'
            }
        };
    },

    computed: {
        mediaDefaultFolderStore() {
            return StateDeprecated.getStore('media_default_folder');
        },

        moduleFactory() {
            return Application.getContainer('factory').module;
        }
    },

    methods: {
        getIconConfigFromFolder(mediaFolder) {
            if (mediaFolder.defaultFolderId === this.lastDefaultFolderId) {
                return this.iconConfig;
            }

            this.lastDefaultFolderId = mediaFolder.defaultFolderId;
            this.mediaDefaultFolderStore.getByIdAsync(mediaFolder.defaultFolderId)
                .then((defaultFolder) => {
                    if (!defaultFolder) {
                        return;
                    }

                    const module = this.moduleFactory.getModuleByEntityName(defaultFolder.entity);
                    if (module) {
                        this.iconConfig.name = module.manifest.icon;
                        this.iconConfig.color = module.manifest.color;
                    }
                });

            return this.iconConfig;
        },

        onChangeName(updatedName, item, endInlineEdit) {
            if (!updatedName || updatedName.trim() === '') {
                this.rejectRenaming(item, 'empty-name', endInlineEdit);
                return;
            }

            item.name = updatedName;
            item.save().then(() => {
                endInlineEdit();
            }).catch((error) => {
                this.rejectRenaming(item, error, endInlineEdit);
            });
        },

        onBlur(event, item, endInlineEdit) {
            const input = event.target.value;
            if (input !== item.name) {
                return;
            }

            if (!input || !input.trim()) {
                this.rejectRenaming(item, 'empty-name', endInlineEdit);
                return;
            }

            endInlineEdit();
        },

        rejectRenaming(item, cause, endInlineEdit) {
            if (cause) {
                let title = this.$tc('global.default.error');
                let message = this.$tc('global.sw-media-folder-item.notification.renamingError.message');

                if (cause === 'empty-name') {
                    title = this.$tc('global.default.error');
                    message = this.$tc('global.sw-media-folder-item.notification.errorBlankItemName.message');
                }

                this.createNotificationError({
                    title: title,
                    message: message
                });
            }

            if (item.isLocal === true) {
                item.delete(true).then(() => {
                    this.$emit('media-folder-remove', [item.id]);
                });
            }
            endInlineEdit();
        },

        navigateToFolder(id) {
            this.$router.push({
                name: 'sw.media.index',
                params: {
                    folderId: id
                }
            });
        },

        openSettings() {
            this.showSettings = true;
        },

        closeSettings() {
            this.showSettings = false;
        },

        openDissolveModal() {
            this.showDissolveModal = true;
        },

        closeDissolveModal() {
            this.showDissolveModal = false;
        },

        openDeleteModal() {
            this.showDeleteModal = true;
        },

        closeDeleteModal() {
            this.showDeleteModal = false;
        },

        emitItemDeleted(deletePromise) {
            this.closeDeleteModal();
            deletePromise.then((ids) => {
                this.$emit('media-folder-delete', ids.folderIds);
            });
        },

        onFolderDissolved(dissolvePromise) {
            this.closeDissolveModal();
            dissolvePromise.then((ids) => {
                this.$emit('media-folder-dissolve', ids);
            });
        },

        onFolderMoved(movePromise) {
            this.closeMoveModal();
            movePromise.then((ids) => {
                this.$emit('media-folder-move', ids);
            });
        },

        openMoveModal() {
            this.showMoveModal = true;
        },

        closeMoveModal() {
            this.showMoveModal = false;
        }
    }
});
