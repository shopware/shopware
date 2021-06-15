import template from './sw-media-modal-folder-settings.html.twig';
import './sw-media-modal-folder-settings.scss';

const { Component, Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
Component.register('sw-media-modal-folder-settings', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        folder: {
            required: true,
            type: Object,
            validator(value) {
                return value.getEntityName() === 'media_folder';
            },
        },
        disabled: {
            required: false,
            type: Boolean,
            default: false,
        },
    },

    data() {
        return {
            modalClass: 'sw-media-modal-folder-settings--shows-overflow',
            thumbnailSizes: [],
            isEditThumbnails: false,
            parent: null,
            configuration: null,
            mediaFolderConfigurationThumbnailSizeRepository: null,
            originalConfiguration: null,
            // eslint-disable-next-line vue/no-dupe-keys
            // disabled: false
        };
    },

    computed: {
        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },
        mediaDefaultFolderRepository() {
            return this.repositoryFactory.create('media_default_folder');
        },
        mediaThumbnailSizeRepository() {
            return this.repositoryFactory.create('media_thumbnail_size');
        },
        mediaFolderConfigurationRepository() {
            return this.repositoryFactory.create('media_folder_configuration');
        },
        notEditable() {
            return this.folder.useParentConfiguration
                || !this.configuration.createThumbnails
                || this.disabled;
        },

        thumbnailListClass() {
            return {
                'is--editable': this.isEditThumbnails,
            };
        },

        labelToggleButton() {
            return this.isEditThumbnails ?
                this.$tc('global.sw-media-modal-folder-settings.labelStopEdit') :
                this.$tc('global.sw-media-modal-folder-settings.labelEditList');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            await this.getThumbnailSizes();
            this.configuration = await this.mediaFolderConfigurationRepository.get(this.folder.configurationId, Context.api);

            this.mediaFolderConfigurationThumbnailSizeRepository = this.repositoryFactory.create(
                this.configuration.mediaThumbnailSizes.entity,
                this.configuration.mediaThumbnailSizes.source,
            );

            this.configuration.mediaThumbnailSizes = await this.mediaFolderConfigurationThumbnailSizeRepository
                .search(new Criteria(), Context.api);

            if (this.folder.parentId !== null) {
                this.parent = await this.mediaFolderRepository.get(this.folder.parentId, Context.api);
                this.parent.configuration = await this.mediaFolderConfigurationRepository
                    .get(this.parent.configurationId, Context.api);
            }
        },

        getItemName(item) {
            const entityNameIdentifier = `global.entities.${item.entity}`;

            return `${this.$tc(entityNameIdentifier)} ${this.$tc('global.entities.media', 2)}`;
        },

        async getThumbnailSizes() {
            const criteria = new Criteria()
                .setLimit(50)
                .setPage(1)
                .addSorting(Criteria.sort('width'));

            this.thumbnailSizes = await this.mediaThumbnailSizeRepository.search(criteria, Context.api);
        },

        toggleEditThumbnails() {
            this.isEditThumbnails = !this.isEditThumbnails;
        },

        async addThumbnail({ width, height }) {
            if (this.checkIfThumbnailExists({ width, height })) {
                return;
            }

            const thumbnailSize = this.mediaThumbnailSizeRepository.create(Context.api);
            thumbnailSize.width = width;
            thumbnailSize.height = height;

            await this.mediaThumbnailSizeRepository.save(thumbnailSize, Context.api);
            this.getThumbnailSizes();
        },

        checkIfThumbnailExists({ width, height }) {
            const exists = this.thumbnailSizes.some((size) => {
                return size.width === width && size.height === height;
            });

            this.disabled = exists;

            return exists;
        },

        async deleteThumbnail(thumbnailSize) {
            if (await this.mediaFolderConfigurationThumbnailSizeRepository.get(thumbnailSize.id, Context.api)) {
                await this.mediaFolderConfigurationThumbnailSizeRepository.delete(thumbnailSize.id, Context.api);
            }

            this.configuration.mediaThumbnailSizes.remove(thumbnailSize.id);
            await this.mediaThumbnailSizeRepository.delete(thumbnailSize.id, Context.api);
            this.getThumbnailSizes();
        },

        isThumbnailSizeActive(size) {
            if (!this.configuration.mediaThumbnailSizes) {
                return false;
            }

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
                this.configuration.mediaThumbnailSizes.add(size);
                return;
            }

            this.configuration.mediaThumbnailSizes.remove(size.id);
        },

        async onChangeInheritance(value) {
            if (value === true) {
                this.originalConfiguration = this.configuration;
                this.configuration = this.parent.configuration;

                return;
            }

            if (this.originalConfiguration) {
                this.configuration = this.originalConfiguration;

                return;
            }

            const newConfiguration = this.mediaFolderConfigurationRepository.create();
            Object.keys(this.configuration).forEach((key) => {
                if (key === 'id') {
                    return;
                }
                newConfiguration[key] = this.configuration[key];
            });
            this.configuration = newConfiguration;
        },

        async onClickSave() {
            this.folder.configurationId = this.configuration.id;

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

            if (this.folder.defaultFolderId) {
                await this.ensureUniqueDefaultFolder(this.folder.id, this.folder.defaultFolderId);
            } else {
                this.folder.defaultFolderId = null;
            }

            try {
                await this.mediaFolderConfigurationRepository.save(this.configuration)
                    .then(() => {
                        // Delete the original configuration if we inherit again
                        if (this.originalConfiguration && this.configuration.id === this.parent.configuration.id) {
                            this.mediaFolderConfigurationRepository.delete(this.originalConfiguration.id);
                        }
                    });

                if (this.folder && this.folder.getEntityName) {
                    await this.mediaFolderRepository.save(this.folder, Context.api);
                }

                this.createNotificationSuccess({
                    title: this.$root.$tc('global.default.success'),
                    message: this.$root.$tc(
                        'global.sw-media-modal-folder-settings.notification.success.message',
                    ),
                });

                this.$nextTick(() => {
                    this.$emit('media-settings-modal-save', this.folder);
                });
            } catch (e) {
                this.createNotificationError({
                    title: this.$root.$tc('global.default.error'),
                    message: this.$root.$tc(
                        'global.sw-media-modal-folder-settings.notification.error.message',
                    ),
                });
            }
        },

        async ensureUniqueDefaultFolder(folderId, defaultFolderId) {
            const criteria = new Criteria()
                .addFilter(
                    Criteria.multi('and', [
                        Criteria.equals('defaultFolderId', defaultFolderId),
                        Criteria.not('or', [Criteria.equals('id', folderId)]),
                    ]),
                );

            const items = await this.mediaFolderRepository.search(criteria, Context.api);

            await Promise.all(items.map((folder) => {
                folder.defaultFolderId = null;
                return this.mediaFolderRepository.save(folder, Context.api);
            }));
        },

        onClickCancel(originalDomEvent) {
            this.mediaFolderRepository.discard(this.folder);

            this.closeModal(originalDomEvent);
        },

        closeModal(originalDomEvent) {
            this.$emit('media-settings-modal-close', { originalDomEvent });
        },

        onInputDefaultFolder(defaultFolderId) {
            this.folder.defaultFolderId = defaultFolderId;
        },
    },
});
