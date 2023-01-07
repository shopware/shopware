import template from './sw-media-folder-item.html.twig';
import './sw-media-folder-item.scss';

const { Application, Mixin, Context } = Shopware;

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inheritAttrs: false,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        isParent: {
            type: Boolean,
            required: false,
            default: false,
        },
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
                color: 'inherit',
            },
        };
    },

    computed: {
        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },

        mediaDefaultFolderRepository() {
            return this.repositoryFactory.create('media_default_folder');
        },

        moduleFactory() {
            return Application.getContainer('factory').module;
        },

        mediaFolder() {
            return this.$attrs.item;
        },

        iconName() {
            switch (this.iconConfig.name) {
                case 'regular-box':
                    return 'multicolor-folder-thumbnail--green';
                case 'regular-products':
                    return 'multicolor-folder-thumbnail--green';
                case 'regular-database':
                    return 'multicolor-folder-thumbnail--grey';
                case 'regular-content':
                    return 'multicolor-folder-thumbnail--pink';
                case 'regular-cog':
                    return 'multicolor-folder-thumbnail--grey';
                default:
                    return 'multicolor-folder-thumbnail';
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getIconConfigFromFolder();
        },

        async getIconConfigFromFolder() {
            const { mediaFolder } = this;

            if (mediaFolder.defaultFolderId === this.lastDefaultFolderId) {
                return;
            }

            this.lastDefaultFolderId = mediaFolder.defaultFolderId;
            const defaultFolder = await this.mediaDefaultFolderRepository.get(mediaFolder.defaultFolderId, Context.api);

            if (!defaultFolder) {
                return;
            }

            const module = this.moduleFactory.getModuleByEntityName(defaultFolder.entity);
            this.iconConfig.name = module.manifest.icon;
            this.iconConfig.color = module.manifest.color;
        },

        async onChangeName(updatedName, item, endInlineEdit) {
            if (!updatedName || !updatedName.trim()) {
                this.rejectRenaming(item, 'empty-name', endInlineEdit);
                return;
            }

            if (updatedName.includes('<')) {
                this.rejectRenaming(item, 'invalid-name', endInlineEdit);
                return;
            }

            item.name = updatedName;

            try {
                await this.mediaFolderRepository.save(item, Context.api);
                item._isNew = false;
            } catch (error) {
                this.rejectRenaming(item, error, endInlineEdit);
            } finally {
                endInlineEdit();
            }
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
                } else if (cause === 'invalid-name') {
                    title = this.$tc('global.default.error');
                    message = this.$tc('global.sw-media-folder-item.notification.errorInvalidItemName.message');
                }

                this.createNotificationError({
                    title: title,
                    message: message,
                });
            }

            if (item.isNew() === true) {
                this.$emit('media-folder-remove', [item.id]);
            }
            endInlineEdit();
        },

        navigateToFolder(id) {
            this.$router.push({
                name: 'sw.media.index',
                params: {
                    folderId: id,
                },
            });
        },

        openSettings() {
            this.showSettings = true;
        },

        closeSettings(mediaFolderChanged) {
            this.showSettings = false;

            // The boolean check if necessary, because sometimes the original html event is passed as an argument
            if (typeof mediaFolderChanged === 'boolean' && mediaFolderChanged === true) {
                this.$nextTick(() => {
                    this.$emit('media-folder-changed');
                });
            }
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

        emitItemDeleted(ids) {
            this.closeDeleteModal();

            this.$nextTick(() => {
                this.$emit('media-folder-delete', ids.folderIds);
            });
        },

        onFolderDissolved(ids) {
            this.closeDissolveModal();

            this.$nextTick(() => {
                this.$emit('media-folder-dissolve', ids);
            });
        },

        onFolderMoved(ids) {
            this.closeMoveModal();

            this.$nextTick(() => {
                this.$emit('media-folder-move', ids);
            });
        },

        openMoveModal() {
            this.showMoveModal = true;
        },

        closeMoveModal() {
            this.showMoveModal = false;
        },

        async refreshIconConfig() {
            await this.getIconConfigFromFolder();
            this.closeSettings(true);
        },
    },
};
