import template from './sw-theme-manager-detail.html.twig';
import './sw-theme-manager-detail.scss';

const { Component, Mixin } = Shopware;
const Criteria = Shopware.Data.Criteria;
const { getObjectDiff, cloneDeep } = Shopware.Utils.object;

Component.register('sw-theme-manager-detail', {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('theme'),
        Mixin.getByName('notification')
    ],

    data() {
        return {
            theme: null,
            parentTheme: null,
            defaultMediaFolderId: null,
            structuredThemeFields: {},
            themeConfig: {},
            showResetModal: false,
            showSaveModal: false,
            errorModalMessage: null,
            baseThemeConfig: {},
            isLoading: false,
            isSaveSuccessful: false,
            mappedFields: {
                color: 'colorpicker',
                fontFamily: 'text'
            },
            defaultTheme: null,
            themeCompatibleSalesChannels: [],
            salesChannelsWithTheme: null,
            newAssignedSalesChannels: [],
            overwrittenSalesChannelAssignments: [],
            removedSalesChannels: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.themeName)
        };
    },

    computed: {
        themeName() {
            if (this.theme) {
                return this.theme.name;
            }

            return '';
        },

        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        defaultFolderRepository() {
            return this.repositoryFactory.create('media_default_folder');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        previewMedia() {
            if (this.theme && this.theme.previewMedia && this.theme.previewMedia.id && this.theme.previewMedia.url) {
                return {
                    'background-image': `url('${this.theme.previewMedia.url}')`,
                    'background-size': 'cover'
                };
            }

            return {
                'background-image': this.defaultThemeAsset
            };
        },

        defaultThemeAsset() {
            return `url('${Shopware.Context.api.assetsPath}/administration/static/img/theme/default_theme_preview.jpg')`;
        },

        deleteDisabledToolTip() {
            return {
                showDelay: 300,
                message: this.$tc('sw-theme-manager.actions.deleteDisabledToolTip'),
                disabled: this.theme.salesChannels.length === 0
            };
        },

        themeId() {
            return this.$route.params.id;
        },

        shouldShowContent() {
            return Object.values(this.structuredThemeFields).length > 0;
        },

        hasMoreThanOneTab() {
            return Object.values(this.structuredThemeFields.tabs).length > 1;
        },

        isDefaultTheme() {
            return this.theme.id === this.defaultTheme.id;
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        themeId() {
            this.getTheme();
        }
    },

    methods: {
        createdComponent() {
            this.getTheme();
            this.setPageContext();
        },

        getTheme() {
            if (!this.themeId) {
                return;
            }

            this.isLoading = true;

            const criteria = new Criteria();
            criteria.addAssociation('previewMedia');
            criteria.addAssociation('salesChannels');

            this.themeRepository.get(this.themeId, Shopware.Context.api, criteria).then((response) => {
                this.theme = response;

                this.getThemeConfig();

                if (this.theme.parentThemeId) {
                    this.getParentTheme();
                }

                this.isLoading = false;
            });
        },

        getThemeConfig() {
            this.isLoading = true;

            if (!this.theme || !this.themeId) {
                return;
            }

            this.themeService.getStructuredFields(this.themeId).then((fields) => {
                this.structuredThemeFields = fields;
            });

            this.themeService.getConfiguration(this.themeId).then((config) => {
                this.themeConfig = config.fields;
                this.baseThemeConfig = cloneDeep(config.fields);
                this.isLoading = false;
            });
        },

        setPageContext() {
            this.getDefaultTheme().then((defaultTheme) => {
                this.defaultTheme = defaultTheme;
            });

            this.getDefaultFolderId().then((folderId) => {
                this.defaultMediaFolderId = folderId;
            });

            this.getThemeCompatibleSalesChannels().then((ids) => {
                this.themeCompatibleSalesChannels = ids;
            });

            this.getSalesChannelsWithTheme().then((salesChannels) => {
                this.salesChannelsWithTheme = salesChannels;
            });
        },

        getParentTheme() {
            this.themeRepository.get(this.theme.parentThemeId, Shopware.Context.api).then((parentTheme) => {
                this.parentTheme = parentTheme;
            });
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        onAddMediaToTheme(mediaItem, context) {
            this.setMediaItem(mediaItem, context);
        },

        onDropMedia(dragData, context) {
            this.setMediaItem(dragData, context);
        },

        setMediaItem(mediaItem, context) {
            context.value = mediaItem.id;
        },

        successfulUpload(mediaItem, context) {
            this.mediaRepository
                .get(mediaItem.targetId, Shopware.Context.api)
                .then((media) => {
                    this.setMediaItem(media, context);
                    return true;
                });
        },

        removeMediaItem(field) {
            field.value = null;
        },

        onReset() {
            if (!this.acl.can('theme.editor')) {
                return;
            }

            if (this.theme.configValues === null) {
                return;
            }

            this.showResetModal = true;
        },

        onCloseResetModal() {
            this.showResetModal = false;
        },

        onCloseErrorModal() {
            this.errorModalMessage = null;
        },

        onConfirmThemeReset() {
            if (!this.acl.can('theme.editor')) {
                return;
            }

            this.themeService.resetTheme(this.themeId).then(() => {
                this.getTheme();
            });

            this.showResetModal = false;
        },

        onSave() {
            this.findChangedSalesChannels();

            if (this.theme.salesChannels.length > 0 || this.removedSalesChannels.length > 0) {
                this.showSaveModal = true;

                return;
            }

            return this.onSaveTheme();
        },

        onCloseSaveModal() {
            this.showSaveModal = false;
        },

        onConfirmThemeSave() {
            this.onSaveTheme();
            this.showSaveModal = false;
        },

        onSaveTheme() {
            if (!this.acl.can('theme.editor')) {
                return;
            }

            this.isSaveSuccessful = false;
            this.isLoading = true;

            return Promise.all([this.saveSalesChannels(), this.saveThemeConfig()]).then(() => {
                this.getTheme();
            }).catch((error) => {
                this.isLoading = false;

                const actions = [];

                const errorObject = error.response.data.errors[0];
                if (errorObject.code === 'THEME__COMPILING_ERROR') {
                    actions.push({
                        label: this.$tc('sw-theme-manager.detail.showFullError'),
                        method: function showFullError() {
                            this.errorModalMessage = errorObject.detail;
                        }.bind(this)
                    });
                }

                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: error.toString(),
                    autoClose: false,
                    actions: [...actions]
                });
            });
        },

        saveSalesChannels() {
            const promises = [];

            if (this.newAssignedSalesChannels.length > 0) {
                this.newAssignedSalesChannels.forEach((salesChannelId) => {
                    promises.push(this.themeService.assignTheme(this.themeId, salesChannelId));
                });
            }

            if (this.removedSalesChannels.length > 0) {
                this.removedSalesChannels.forEach((salesChannel) => {
                    promises.push(this.themeService.assignTheme(this.defaultTheme.id, salesChannel.id));
                });
            }

            return Promise.all(promises);
        },

        findChangedSalesChannels() {
            this.newAssignedSalesChannels = [];
            this.removedSalesChannels = [];
            this.overwrittenSalesChannelAssignments = [];

            const diff = this.themeRepository.getSyncChangeset([this.theme]);

            if (diff.changeset.length > 0 && diff.changeset[0].changes.hasOwnProperty('salesChannels')) {
                this.findAddedSalesChannels(diff.changeset[0].changes.salesChannels);
            }

            if (diff.deletions.length > 0) {
                this.findRemovedSalesChannels(diff.deletions);
            }
        },

        findAddedSalesChannels(salesChannels) {
            salesChannels.forEach((salesChannel) => {
                this.newAssignedSalesChannels.push(salesChannel.id);

                const overwrittenSalesChannel = this.salesChannelsWithTheme.get(salesChannel.id);
                if (overwrittenSalesChannel !== null) {
                    this.overwrittenSalesChannelAssignments.push({
                        id: salesChannel.id,
                        salesChannelName: this.theme.salesChannels.get(salesChannel.id).translated.name,
                        oldThemeName: overwrittenSalesChannel.extensions.themes[0].name
                    });
                }
            });
        },

        findRemovedSalesChannels(salesChannels) {
            salesChannels.forEach((salesChannel) => {
                this.removedSalesChannels.push({
                    id: salesChannel.key,
                    name: this.theme.getOrigin().salesChannels.get(salesChannel.key).translated.name
                });
            });
        },

        saveThemeConfig() {
            const newValues = getObjectDiff(this.baseThemeConfig, this.themeConfig);

            return this.themeService.updateTheme(this.themeId, { config: newValues });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSearch(value = null) {
            if (!value.length || value.length <= 0) {
                this.term = null;
            } else {
                this.term = value;
            }
        },

        mapSwFieldTypes(field) {
            return !this.mappedFields[field] ? null : this.mappedFields[field];
        },

        getThemeCompatibleSalesChannels() {
            const criteria = new Criteria();
            criteria.addAssociation('type');
            criteria.addFilter(Criteria.equalsAny('type.name', ['Storefront', 'Headless']));

            return this.salesChannelRepository.search(criteria, Shopware.Context.api).then((searchResult) => {
                return searchResult.getIds();
            });
        },

        getSalesChannelsWithTheme() {
            const criteria = new Criteria();
            criteria.addAssociation('themes');
            criteria.addFilter(Criteria.not('or', [
                Criteria.equals('themes.id', null),
            ]));

            return this.salesChannelRepository.search(criteria, Shopware.Context.api).then((searchResult) => {
                return searchResult;
            });
        },

        getDefaultFolderId() {
            const criteria = new Criteria(1, 1);
            criteria.addAssociation('folder');
            criteria.addFilter(Criteria.equals('entity', this.themeRepository.schema.entity));

            return this.defaultFolderRepository.search(criteria, Shopware.Context.api).then((searchResult) => {
                const defaultFolder = searchResult.first();
                if (defaultFolder.folder.id) {
                    return defaultFolder.folder.id;
                }

                return null;
            });
        },

        getDefaultTheme() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('technicalName', 'Storefront'));

            return this.themeRepository.search(criteria, Shopware.Context.api).then((response) => {
               return response.first();
            });
        },

        /**
         *  Convert the field to the right structure for the form field renderer:
         *  bind: {
         *      type: field.type,
         *      config: anything else from field, including field.custom
         *  }
         */
        getBind(field) {
            const config = Object.assign({}, field);

            delete config.type;

            Object.assign(config, config.custom);
            delete config.custom;

            return { type: field.type, config: config };
        },

        selectionDisablingMethod(selection) {
            if (!this.isDefaultTheme) {
                return false;
            }

            return this.theme.getOrigin().salesChannels.has(selection.id);
        },

        isThemeCompatible(item) {
            return this.themeCompatibleSalesChannels.includes(item.id);
        },
    }
});
