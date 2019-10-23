import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-media-modal-folder-settings.html.twig';
import './sw-media-modal-folder-settings.scss';

const { Component, Mixin, StateDeprecated } = Shopware;

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
                return value.getEntityName() === 'media_folder';
            }
        }
    },

    data() {
        return {
            modalClass: 'sw-media-modal-folder-settings--shows-overflow',
            thumbnailSizes: [],
            isEditThumbnails: false,
            parent: null,
            configuration: null,
            originalConfiguration: null
        };
    },

    computed: {
        mediaFolderStore() {
            return StateDeprecated.getStore('media_folder');
        },

        mediaThumbnailSizeStore() {
            return StateDeprecated.getStore('media_thumbnail_size');
        },

        mediaDefaultFolderStore() {
            return StateDeprecated.getStore('media_default_folder');
        },

        mediaFolderConfigurationStore() {
            return StateDeprecated.getStore('media_folder_configuration');
        },

        mediaFolderConfigurationThumbnailSizeStore() {
            return this.configuration.getAssociation('mediaThumbnailSizes');
        },

        notEditable() {
            return this.folder.useParentConfiguration || !this.configuration.createThumbnails;
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
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.getThumbnailSizes();
            this.configuration = this.mediaFolderConfigurationStore.getById(this.folder.configurationId);
            this.mediaFolderConfigurationThumbnailSizeStore.getList({
                limit: 25,
                page: 1
            });

            if (this.folder.parentId !== null) {
                this.parent = this.mediaFolderStore.getById(this.folder.parentId);
                this.mediaFolderConfigurationStore.getByIdAsync(this.parent.configurationId).then((parentConfiguration) => {
                    this.parent.configuration = parentConfiguration;
                    this.parent.configuration.getAssociation('mediaThumbnailSizes').getList({
                        limit: 25,
                        page: 1
                    });
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
            this.mediaFolderConfigurationThumbnailSizeStore.remove(thumbnailSize);

            thumbnailSize.delete(true).then(() => {
                this.getThumbnailSizes();
            });
        },

        isThumbnailSizeActive(size) {
            return this.configuration.mediaThumbnailSizes.some((value) => {
                return value.id === size.id;
            });
        },

        thumbnailSizeCheckboxName(size) {
            return `thumbnail-size-${size.width}-${size.height}-active`;
        },

        onActiveTabChanged(activeTab) {
            if (activeTab === 'settings') {
                this.modalClass = 'sw-media-modal-folder-settings--shows-overflow';
                return;
            }
            this.modalClass = '';
        },

        onChangeThumbnailSize(value, size) {
            if (value === true) {
                const mapping = this.mediaFolderConfigurationThumbnailSizeStore.create(size.id);
                this.configuration.mediaThumbnailSizes.push(mapping);

                return;
            }

            const thumbnailSizeIndex = this.configuration.mediaThumbnailSizes.findIndex((storedSize) => {
                return storedSize.id === size.id;
            });

            if (thumbnailSizeIndex === -1) {
                return;
            }

            const removedThumbnailSizes = this.configuration.mediaThumbnailSizes.splice(thumbnailSizeIndex, 1);
            removedThumbnailSizes.forEach((thumbnailSize) => {
                thumbnailSize.delete();
            });
        },

        onChangeInheritance(value) {
            if (value === true) {
                this.originalConfiguration = this.configuration;
                this.configuration = this.parent.configuration;
                this.originalConfiguration.delete();

                return;
            }

            if (this.originalConfiguration) {
                this.configuration = this.originalConfiguration;
                this.configuration.isDeleted = false;

                return;
            }

            this.configuration = this.mediaFolderConfigurationStore.duplicate(this.parent.configurationId, true);
        },

        onClickSave() {
            this.folder.configuration.id = this.configuration.id;

            // if the config is created all properties that are null won't be sent to the server
            // this leads to setting default values for this properties on the server side
            // these properties are null because the value of an unchecked checkbox is null
            // ToDo fix this with NEXT-1544
            if (this.configuration.keepAspectRatio === null) {
                this.configuration.keepAspectRatio = false;
            }

            if (this.configuration.createThumbnails === null) {
                this.configuration.createThumbnails = false;
            }

            let handleDefaultFolder = Promise.resolve();

            if (this.folder.defaultFolderId) {
                handleDefaultFolder = this.ensureUniqueDefaultFolder(this.folder.id, this.folder.defaultFolderId);
            } else {
                this.folder.defaultFolderId = null;
            }

            handleDefaultFolder.then(() => {
                this.configuration.save()
                    .then(() => {
                        return this.folder.save();
                    })
                    .then(() => {
                        this.mediaFolderConfigurationThumbnailSizeStore.forEach((association) => {
                            if (association.isDeleted) {
                                this.mediaFolderConfigurationThumbnailSizeStore.remove(association);
                            }
                        });

                        this.createNotificationSuccess({
                            title: this.$root.$tc('global.default.success'),
                            message: this.$root.$tc(
                                'global.sw-media-modal-folder-settings.notification.success.message'
                            )
                        });
                    })
                    .catch(() => {
                        this.createNotificationError({
                            title: this.$root.$tc('global.default.error'),
                            message: this.$root.$tc(
                                'global.sw-media-modal-folder-settings.notification.error.message'
                            )
                        });
                    });

                this.$emit('media-settings-modal-save', this.folder);
            });
        },

        ensureUniqueDefaultFolder(folderId, defaultFolderId) {
            const criteria = CriteriaFactory.multi(
                'AND',
                CriteriaFactory.equals('media_folder.defaultFolderId', defaultFolderId),
                CriteriaFactory.not(
                    'OR',
                    CriteriaFactory.equals('media_folder.id', folderId)
                )
            );
            return this.mediaFolderStore.getList({ criteria })
                .then(({ items }) => {
                    const updates = items.map((folder) => {
                        folder.defaultFolderId = null;
                        return folder.save();
                    });
                    return Promise.all(updates);
                });
        },

        onClickCancel(originalDomEvent) {
            this.folder.discardChanges();
            this.closeModal(originalDomEvent);
        },

        closeModal(originalDomEvent) {
            this.$emit('media-settings-modal-close', { originalDomEvent });
        },

        onInputDefaultFolder(defaultFolderId) {
            this.folder.defaultFolderId = defaultFolderId;
        }
    }
});
