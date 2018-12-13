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
            this.folder.save().then(() => {
                configuration.save().then(() => {
                    this.mediaFolderConfigurationStore.sync(true).then(() => {
                        this.createNotificationSuccess({
                            message: notificationMessageSuccess
                        });
                    }).catch(() => {
                        this.createNotificationError({
                            message: notificationMessageError
                        });
                    });
                }).catch(() => {
                    this.createNotificationError({
                        message: notificationMessageError
                    });
                });
            }).catch(() => {
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
        }
    }
});
