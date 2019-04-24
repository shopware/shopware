import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-manufacturer-detail.html.twig';
import './sw-manufacturer-detail.scss';

Component.register('sw-manufacturer-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('manufacturer')
    ],

    data() {
        return {
            manufacturerId: null,
            manufacturer: { isLoading: true },
            mediaItem: null,
            attributeSets: []
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.manufacturer, 'name');
        },

        manufacturerStore() {
            return State.getStore('product_manufacturer');
        },

        mediaStore() {
            return State.getStore('media');
        },

        attributeSetStore() {
            return State.getStore('attribute_set');
        },

        mediaUploadTag() {
            return `sw-manufacturer-detail--${this.manufacturer.id}`;
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.manufacturerId = this.$route.params.id;
                if (this.manufacturer && this.manufacturer.isLocal) {
                    return;
                }

                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.manufacturerStore.getByIdAsync(this.manufacturerId).then((manufacturer) => {
                this.manufacturer = manufacturer;
                if (manufacturer.mediaId) {
                    this.mediaItem = this.mediaStore.getById(this.manufacturer.mediaId);
                }
            });

            this.attributeSetStore.getList({
                page: 1,
                limit: 100,
                criteria: CriteriaFactory.equals('relations.entityName', 'product_manufacturer'),
                associations: {
                    attributes: {
                        limit: 100,
                        sort: 'attribute.config.attributePosition'
                    }
                }
            }, true).then(({ items }) => {
                this.attributeSets = items;
            });
        },

        abortOnLanguageChange() {
            return this.manufacturer.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        setMediaItem({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((updatedMedia) => {
                this.mediaItem = updatedMedia;
            });
            this.manufacturer.mediaId = targetId;
        },

        setMediaFromSidebar(media) {
            this.manufacturer.mediaId = media.id;
            this.mediaItem = media;
        },

        onUnlinkLogo() {
            this.mediaItem = null;
            this.manufacturer.mediaId = null;
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        },

        onSave() {
            const manufacturerName = this.manufacturer.name || this.manufacturer.translated.name;
            const titleSaveSuccess = this.$tc('sw-manufacturer.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-manufacturer.detail.messageSaveSuccess', 0, { name: manufacturerName });
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: manufacturerName }
            );

            this.manufacturer.save().then(() => {
                this.$refs.mediaSidebarItem.getList();
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, exception.message, exception.response);
                throw exception;
            });
        }
    }
});
