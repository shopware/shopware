import { Mixin, State } from 'src/core/shopware';
import template from './sw-media-modal-folder-settings.html.twig';
import './sw-media-modal-folder-settings.scss';

/**
 * @private
 */
export default {
    name: 'sw-media-modal-folder-settings',
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
            return State.getStore('media_folder');
        },

        mediaThumbnailSizeStore() {
            return State.getStore('media_thumbnail_size');
        },

        mediaDefaultFolderStore() {
            return State.getStore('media_default_folder');
        },

        mediaFolderConfigurationStore() {
            return State.getStore('media_folder_configuration');
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
        this.componentCreated();
    },

    methods: {
        componentCreated() {
            this.getThumbnailSizes();
            this.configuration = this.mediaFolderConfigurationStore.getById(this.folder.configuration.id);
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
            return this.configuration.mediaThumbnailSizes.some((value) => {
                return value.id === size.id;
            });
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

            this.configuration = this.mediaFolderConfigurationStore.duplicate(this.parent.configuration.id, true);
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

            this.mediaFolderConfigurationStore.sync()
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
                        title: this.$root.$tc(
                            'global.sw-media-modal-folder-settings.notification.success.title'
                        ),
                        message: this.$root.$tc(
                            'global.sw-media-modal-folder-settings.notification.success.message'
                        )
                    });
                })
                .catch(() => {
                    this.createNotificationError({
                        title: this.$root.$tc(
                            'global.sw-media-modal-folder-settings.notification.error.title'
                        ),
                        message: this.$root.$tc(
                            'global.sw-media-modal-folder-settings.notification.error.message'
                        )
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

        onInputDefaultFolder(defaultFolderId) {
            this.folder.defaultFolderId = defaultFolderId;
        }
    }
};
