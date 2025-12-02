import { isPlayableMediaFormat, shouldShowUnsupportedFormatWarning } from 'src/app/service/media-format.service';
import template from './sw-media-quickinfo.html.twig';
import './sw-media-quickinfo.scss';

const { Mixin, Context, Utils } = Shopware;
const { dom, format } = Utils;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'mediaService',
        'repositoryFactory',
        'acl',
        'customFieldDataProviderService',
        'systemConfigApiService',
    ],

    emits: [
        'media-item-rename-success',
        'media-item-replaced',
        'update:item',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('media-sidebar-modal-mixin'),
        Mixin.getByName('placeholder'),
    ],

    props: {
        item: {
            required: true,
            type: Object,
            validator(value) {
                return value.getEntityName() === 'media';
            },
        },

        editable: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            customFieldSets: [],
            isLoading: false,
            isSaveSuccessful: false,
            showModalReplace: false,
            fileNameError: null,
            arReady: false,
            defaultArReady: false,
            arPlacement: 'horizontal',
            defaultArPlacement: 'horizontal',
            arPlacementOptions: [],
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        isMediaObject() {
            return this.item.type === 'media';
        },

        fileSize() {
            return format.fileSize(this.item.fileSize);
        },

        createdAt() {
            const date = this.item.uploadedAt || this.item.createdAt;
            return format.date(date);
        },

        fileNameClasses() {
            return {
                'has--error': this.fileNameError,
            };
        },

        /**
         * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
         */
        isSpatial() {
            // we need to check the media url since media.fileExtension is set directly after upload
            return this.item?.fileExtension === 'glb' || !!this.item?.url?.endsWith('.glb');
        },

        extensionSdkButtons() {
            return Shopware.Store.get('actionButtons').buttons.filter((button) => {
                if (button.entity !== 'media' || button.view !== 'item') {
                    return false;
                }

                return (
                    !button.fileTypes?.length ||
                    button.fileTypes.some((type) => {
                        return type.toLowerCase() === this.item.fileExtension.toLowerCase();
                    })
                );
            });
        },

        isPlayable() {
            return isPlayableMediaFormat(this.item.mimeType);
        },

        showUnsupportedFormatWarning() {
            return shouldShowUnsupportedFormatWarning(this.item.mimeType);
        },
    },

    watch: {
        'item.id': {
            handler() {
                this.fetchSpatialItemConfig();
                this.fileNameError = null;
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadCustomFieldSets();
            this.fetchSpatialItemConfig();
        },

        /**
         * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
         */
        fetchSpatialItemConfig() {
            this.systemConfigApiService.getValues('core.media').then((values) => {
                this.defaultArReady = values['core.media.defaultEnableAugmentedReality'];
                this.defaultArPlacement = values['core.media.defaultARPlacement'];
            });

            this.systemConfigApiService.getConfig('core.media').then((config) => {
                config
                    .flat()[0]
                    .elements.filter((element) => element.name === 'core.media.defaultARPlacement')
                    .forEach((element) => {
                        this.arPlacementOptions = element.config.options.map((option) => {
                            return {
                                id: option.id,
                                value: option.id,
                                label: this.$tc(`sw-media.sidebar.actions.${option.id}`),
                            };
                        });
                    });
            });

            this.mediaRepository.get(this.item.id, Shopware.Context.api).then((entity) => {
                this.arReady = entity?.config?.spatial?.arReady;
                this.arPlacement = entity?.config?.spatial?.arPlacement;
            });
        },

        /**
         * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
         */
        buildAugmentedRealityTooltip(snippet) {
            const route = { name: 'sw.settings.media.index' };
            const routeData = this.$router.resolve(route);

            const data = {
                settingsLink: routeData.href,
            };

            return this.$tc(snippet, 0, data);
        },

        loadCustomFieldSets() {
            return this.customFieldDataProviderService.getCustomFieldSets('media').then((sets) => {
                this.customFieldSets = sets;
            });
        },

        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            try {
                await this.mediaRepository.save(this.item, Context.api);
                this.isSaveSuccessful = true;
            } catch (error) {
                this.createNotificationError({
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
                Shopware.Utils.EventBus.emit('sw-media-library-item-updated', this.item.id);
            }
        },

        /**
         * @deprecated tag:v6.8.0 - Use `onSave` instead
         */
        async onSaveCustomFields(item) {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            await this.mediaRepository.save(item, Context.api);

            this.isSaveSuccessful = true;
            this.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        async copyLinkToClipboard() {
            if (this.item) {
                try {
                    await dom.copyStringToClipboard(this.item.url);
                    this.createNotificationSuccess({
                        message: this.$tc('sw-media.general.notification.urlCopied.message'),
                    });
                } catch (err) {
                    this.createNotificationError({
                        title: this.$tc('global.default.error'),
                        message: this.$tc('global.sw-field.notification.notificationCopyFailureMessage'),
                    });
                }
            }
        },

        onSubmitTitle(value) {
            this.item.title = value;

            return this.onSave();
        },

        onSubmitAltText(value) {
            this.item.alt = value;

            return this.onSave();
        },

        async onChangeFileName(value) {
            const { item } = this;
            item.isLoading = true;
            this.fileNameError = null;

            try {
                await this.mediaService.renameMedia(item.id, value).catch((error) => {
                    const fileNameErrorCodes = [
                        'CONTENT__MEDIA_EMPTY_FILE',
                        'CONTENT__MEDIA_ILLEGAL_FILE_NAME',
                    ];

                    error.response.data.errors.forEach((e) => {
                        if (this.fileNameError || !fileNameErrorCodes.includes(e.code)) {
                            return;
                        }

                        this.fileNameError = e;
                    });

                    return Promise.reject(error);
                });
                item.fileName = value;

                this.createNotificationSuccess({
                    message: this.$tc('global.sw-media-media-item.notification.renamingSuccess.message'),
                });
                this.$emit('media-item-rename-success', item);
            } catch (exception) {
                const errors = exception.response.data.errors;

                errors.forEach((error) => {
                    this.handleErrorMessage(error);
                });
            } finally {
                item.isLoading = false;
            }
        },

        handleErrorMessage(error) {
            switch (error.code) {
                case 'CONTENT__MEDIA_FILE_NAME_IS_TOO_LONG':
                    this.createNotificationError({
                        message: this.$tc(
                            'global.sw-media-media-item.notification.fileNameTooLong.message',
                            {
                                length: error.meta.parameters.maxLength,
                            },
                            0,
                        ),
                    });
                    break;
                default:
                    this.createNotificationError({
                        message: this.$tc('global.sw-media-media-item.notification.renamingError.message'),
                    });
            }
        },

        openModalReplace() {
            if (!this.acl.can('media.editor')) {
                return;
            }

            this.showModalReplace = true;
        },

        closeModalReplace() {
            this.showModalReplace = false;
        },

        emitRefreshMediaLibrary() {
            this.closeModalReplace();

            this.$nextTick(() => {
                this.$emit('media-item-replaced');
            });
        },

        quickActionClasses(disabled) {
            return [
                'sw-media-sidebar__quickaction',
                {
                    'sw-media-sidebar__quickaction--disabled': disabled,
                },
            ];
        },

        onRemoveFileNameError() {
            this.fileNameError = null;
        },

        /**
         * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
         */
        toggleAR(newValue) {
            const newSpatialConfig = {
                spatial: {
                    arReady: newValue,
                    arPlacement: this.arPlacement,
                    updatedAt: Date.now(),
                },
            };
            const newItemConfig = {
                config: {
                    ...this.item.config,
                    ...newSpatialConfig,
                },
            };

            this.$emit('update:item', { ...this.item, ...newItemConfig });
        },

        /**
         * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
         */
        changeARPlacement(newPlacement) {
            const newSpatialConfig = {
                spatial: {
                    arReady: this.arReady,
                    arPlacement: newPlacement,
                    updatedAt: Date.now(),
                },
            };
            const newItemConfig = {
                config: {
                    ...this.item.config,
                    ...newSpatialConfig,
                },
            };

            this.$emit('update:item', { ...this.item, ...newItemConfig });
        },

        runAppAction(action) {
            if (typeof action.callback !== 'function') {
                return;
            }

            const { fileName, mimeType, fileSize, url, id } = this.item;

            action.callback({
                id,
                url,
                fileName,
                mimeType,
                fileSize,
            });
        },
    },
};
