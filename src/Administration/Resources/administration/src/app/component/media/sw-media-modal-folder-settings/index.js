import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-media-modal-folder-settings.html.twig';
import './sw-media-modal-folder-settings.less';

/**
 * @private
 */
Component.register('sw-media-modal-folder-settings', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        folder: {
            required: true,
            type: Object,
            validator(value) {
                return value.entityName === 'media_folder';
            }
        }
    },

    data() {
        return {
            thumbnailSizes: [],
            isEditThumbnails: false,
            defaultFolder: null,
            parent: null
        };
    },

    computed: {
        mediaFolderStore() {
            return State.getStore('media_folder');
        },

        mediaThumbnailSizeStore() {
            return State.getStore('media_thumbnail_size');
        },

        mediaDefaultFolderStore() {
            return State.getStore('media_default_folder');
        },

        mediaDefaultFolderAssociationStore() {
            return this.folder.getAssociation('defaultFolder');
        },

        mediaFolderConfigurationStore() {
            return State.getStore('media_folder_configuration');
        },

        mediaFolderConfigurationThumbnailSizeStore() {
            return this.folder.configuration.getAssociation('mediaThumbnailSizes');
        },

        notEditable() {
            return this.folder.useParentConfiguration || !this.folder.configuration.createThumbnails;
        },

        thumbnailListClass() {
            return {
                'is--editable': this.isEditThumbnails
            };
        },

        labelToggleButton() {
            return this.isEditThumbnails ?
                this.$tc('global.sw-media-modal-folder-settings.labelStopEdit') :
                this.$tc('global.sw-media-modal-folder-settings.labelEditList');
        }
    },

    created() {
        this.componentCreated();
    },

    methods: {
        componentCreated() {
            this.getThumbnailSizes();
            this.mediaDefaultFolderAssociationStore.getList({
                limit: 1,
                page: 1
            }).then((response) => {
                if (response.items.length > 0) {
                    this.defaultFolder = response.items.shift();
                }
            });
            this.folder.configuration = this.mediaFolderConfigurationStore.getById(this.folder.configuration.id);
            this.mediaFolderConfigurationThumbnailSizeStore.getList({
                limit: 25,
                page: 1
            });

            if (this.folder.parentId !== null) {
                this.parent = this.mediaFolderStore.getById(this.folder.parentId);
                this.parent.configuration = this.mediaFolderConfigurationStore.getById(this.parent.configuration.id);
                this.parent.configuration.getAssociation('mediaThumbnailSizes').getList({
                    limit: 25,
                    page: 1
                });
            }
        },

        getItemName(item) {
            const entityNameIdentifier = `global.entities.${item.entity}`;

            return `${this.$tc(entityNameIdentifier)} ${this.$tc('global.entities.media', 2)}`;
        },

        getThumbnailSizes() {
            this.mediaThumbnailSizeStore.getList({
                limit: 50,
                page: 1,
                sortBy: 'width'
            }).then((response) => {
                this.thumbnailSizes = response.items;
                if (this.thumbnailSizes.length === 0) {
                    this.isEditThumbnails = true;
                }
            });
        },

        toggleEditThumbnails() {
            this.isEditThumbnails = !this.isEditThumbnails;
        },

        addThumbnail({ width, height }) {
            const thumbnailSize = this.mediaThumbnailSizeStore.create();
            thumbnailSize.width = width;
            thumbnailSize.height = height;

            thumbnailSize.save().then(() => {
                this.getThumbnailSizes();
            });
        },

        deleteThumbnail(thumbnailSize) {
            thumbnailSize.delete(true).then(() => {
                this.getThumbnailSizes();
            });
        },

        isThumbnailSizeActive(size) {
            return this.folder.configuration.mediaThumbnailSizes.some((value) => {
                return value.id === size.id;
            });
        },

        onChangeThumbnailSize(value, size) {
            if (value === true) {
                const mapping = this.mediaFolderConfigurationThumbnailSizeStore.create(size.id);
                this.folder.configuration.mediaThumbnailSizes.push(mapping);

                return;
            }

            this.folder.configuration.mediaThumbnailSizes.forEach((savedSize) => {
                if (savedSize.id === size.id) {
                    savedSize.delete();
                }
            });
        },

        onChangeInheritance(value) {
            if (value === true) {
                const configuration = this.folder.configuration;
                this.folder.configuration = this.parent.configuration;
                configuration.delete();

                return;
            }

            this.folder.configuration.override(
                this.mediaFolderConfigurationStore.duplicate(this.folder.configuration.id, true)
            );
        },

        onClickSave() {
            const notificationMessageSuccess = this.$tc('global.sw-media-modal-folder-settings.notificationSuccess');
            const notificationMessageError = this.$tc('global.sw-media-modal-folder-settings.notificationError');

            const configuration = this.folder.configuration;

            const resetDefaultFolder = () => {
                if (this.defaultFolder && this.folder.defaultFolder.length > 0) {
                    const currentFolder = this.folder.defaultFolder.shift();
                    if (currentFolder.id !== this.defaultFolder.id) {
                        const oldFolder = this.mediaDefaultFolderStore.getById(this.defaultFolder.id);
                        oldFolder.folderId = null;
                        return oldFolder.save(false);
                    }
                }

                return Promise.resolve();
            };

            resetDefaultFolder()
                .then(() => {
                    return this.folder.save();
                })
                .then(() => {
                    return configuration.save();
                })
                .then(() => {
                    return this.mediaFolderConfigurationStore.sync(true);
                })
                .then(() => {
                    this.createNotificationSuccess({
                        message: notificationMessageSuccess
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        message: notificationMessageError
                    });
                });

            this.$emit('sw-media-modal-folder-settings-save', this.folder);
        },

        onClickCancel(originalDomEvent) {
            this.folder.discardChanges();
            this.closeModal(originalDomEvent);
        },

        closeModal(originalDomEvent) {
            this.$emit('sw-media-modal-folder-settings-close', { originalDomEvent });
        },

        onInputDefaultFolder(defaultFolder) {
            this.folder.defaultFolder.splice(0);
            this.mediaDefaultFolderAssociationStore.removeAll();
            const folder = this.mediaDefaultFolderAssociationStore.create(defaultFolder);
            this.folder.defaultFolder.push(folder);
        }
    }
});
