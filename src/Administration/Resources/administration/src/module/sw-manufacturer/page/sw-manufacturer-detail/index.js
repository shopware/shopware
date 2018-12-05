import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-manufacturer-detail.html.twig';
import './sw-manufacturer-detail.less';

Component.register('sw-manufacturer-detail', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            manufacturerId: null,
            manufacturer: { isLoading: true },
            mediaItem: null
        };
    },

    computed: {
        manufacturerStore() {
            return State.getStore('product_manufacturer');
        },

        mediaStore() {
            return State.getStore('media');
        },

        logoFieldClasses() {
            const isLogo = this.manufacturer.mediaId || this.manufacturer.isLoading;
            return {
                'sw-manufacturer-image-pane--logo': isLogo
            };
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
                if (this.manufacturer.isLocal) {
                    return;
                }
                this.manufacturerStore.getByIdAsync(this.manufacturerId).then((manufacturer) => {
                    this.manufacturer = manufacturer;
                    if (manufacturer.mediaId) {
                        this.mediaItem = this.mediaStore.getById(this.manufacturer.mediaId);
                    }
                });
            }
        },

        setMediaItem(mediaEntity) {
            this.mediaItem = mediaEntity;
            this.manufacturer.mediaId = mediaEntity.id;
        },

        onUnlinkLogo() {
            this.mediaItem = null;
            this.manufacturer.mediaId = null;
        },

        onSave() {
            const manufacturerName = this.manufacturer.name;
            const titleSaveSuccess = this.$tc('sw-manufacturer.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-manufacturer.detail.messageSaveSuccess', 0, { name: manufacturerName });
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: manufacturerName }
            );

            this.manufacturer.save().then(() => {
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
            });
        }
    }
});
