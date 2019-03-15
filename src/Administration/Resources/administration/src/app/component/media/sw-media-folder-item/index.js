import { Application, Mixin, State } from 'src/core/shopware';
import template from './sw-media-folder-item.html.twig';
import './sw-media-folder-item.scss';

export default {
    name: 'sw-media-folder-item',
    template,
    inheritAttrs: false,

    mixins: [
        Mixin.getByName('selectable-media-item'),
        Mixin.getByName('notification')
    ],

    provide() {
        return {
            renameEntity: this.renameEntity,
            rejectRenaming: this.rejectRenaming
        };
    },

    props: {
        item: {
            type: Object,
            required: true,
            validator(value) {
                return value.getEntityName() === 'media_folder';
            }
        },

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
            iconConfig: {
                name: '',
                color: 'inherit'
            }
        };
    },

    computed: {
        mediaDefaultFolderStore() {
            return State.getStore('media_default_folder');
        },

        baseComponent() {
            return this.$refs.innerComponent;
        },

        routerLink() {
            return {
                name: 'sw.media.index',
                params: {
                    folderId: this.item.id
                }
            };
        },

        moduleFactory() {
            return Application.getContainer('factory').module;
        },

        isDefaultFolder() {
            return !!this.item.defaultFolderId;
        }
    },

    mounted() {
        this.mountedComponent();
    },

    watch: {
        'item.defaultFolderId'() {
            this.updateIconConfig();
        }
    },

    methods: {
        mountedComponent() {
            if (this.item.name === '') {
                this.baseComponent.startInlineEdit();
            }

            this.updateIconConfig();
        },

        updateIconConfig() {
            if (!this.isDefaultFolder) {
                return;
            }

            this.mediaDefaultFolderStore.getByIdAsync(this.item.defaultFolderId).then((defaultFolder) => {
                if (!defaultFolder) {
                    return;
                }

                const module = this.moduleFactory.getModuleByEntityName(defaultFolder.entity);
                if (module) {
                    this.iconConfig = {
                        name: module.manifest.icon,
                        color: module.manifest.color
                    };
                }
            });
        },

        onStartRenaming() {
            this.baseComponent.startInlineEdit();
        },

        renameEntity(updatedName) {
            if (this.item.name === updatedName) {
                return Promise.resolve();
            }

            this.item.isLoading = true;
            this.item.name = updatedName;

            return this.item.save().then(() => {
                this.item.isLoading = false;
                this.$emit('sw-media-folder-rename-successful', this.item);
            }).catch(() => {
                this.rejectRenaming('error');
            });
        },

        rejectRenaming(cause) {
            if (cause) {
                let title = this.$tc('global.sw-media-folder-item.notification.renamingError.title');
                let message = this.$tc('global.sw-media-folder-item.notification.renamingError.message');

                if (cause === 'empty-name') {
                    title = this.$tc('global.sw-media-folder-item.notification.errorBlankItemName.title');
                    message = this.$tc('global.sw-media-folder-item.notification.errorBlankItemName.message');
                }

                this.createNotificationError({
                    title: title,
                    message: message
                });
            }

            if (this.item.isLocal === true) {
                this.item.delete(true).then(() => {
                    this.$emit('sw-media-folder-item-remove', [this.item.id]);
                });
            }
        },

        navigateToFolder() {
            this.$router.push(this.routerLink);
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
                this.$emit('sw-media-folder-item-delete', ids.folderIds);
            });
        },

        onFolderDissolved(dissolvePromise) {
            this.closeDissolveModal();
            dissolvePromise.then((ids) => {
                this.$emit('sw-media-folder-item-dissolve', ids);
            });
        },

        onFolderMoved(movePromise) {
            this.closeMoveModal();
            movePromise.then((ids) => {
                this.$emit('sw-media-media-item-moved', ids);
            });
        },

        openMoveModal() {
            this.showMoveModal = true;
        },

        closeMoveModal() {
            this.showMoveModal = false;
        }
    }
};
