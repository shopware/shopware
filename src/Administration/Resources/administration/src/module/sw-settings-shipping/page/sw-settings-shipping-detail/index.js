import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-settings-shipping-detail.html.twig';
import './sw-settings-shipping-detail.scss';

Component.register('sw-settings-shipping-detail', {
    template,

    inject: ['ruleConditionDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('shippingMethod')
    ],

    watch: {
        'shippingMethod.mediaId'() {
            if (this.shippingMethod.mediaId) {
                this.setMediaItem({ targetId: this.shippingMethod.mediaId });
            }
        }
    },

    data() {
        return {
            shippingMethod: {},
            logoMediaItem: null,
            uploadTag: 'sw-shipping-method-upload-tag'
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.shippingMethod, 'name');
        },

        shippingMethodStore() {
            return State.getStore('shipping_method');
        },

        ruleStore() {
            return State.getStore('rule');
        },

        priceRuleStore() {
            return State.getStore('shipping_method_price');
        },

        mediaStore() {
            return State.getStore('media');
        },

        deliveryTimeStore() {
            return State.getStore('delivery_time');
        },

        isLoading() {
            return Object.keys(this.shippingMethod).length === 0
                || this.shippingMethod.isLoading;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.shippingMethodId = this.$route.params.id;
                this.loadEntityData();
            }
        },

        loadEntityData() {
            this.shippingMethod = this.shippingMethodStore.getById(this.shippingMethodId);
        },

        abortOnLanguageChange() {
            return this.shippingMethod.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.loadEntityData();
        },

        onSave() {
            const shippingMethodName = this.shippingMethod.name;
            const titleSaveSuccess = this.$tc('sw-settings-shipping.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-shipping.detail.messageSaveSuccess', 0, {
                name: shippingMethodName
            });
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: shippingMethodName }
            );

            return this.shippingMethod.save().then(() => {
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
        },

        setMediaItem({ targetId }) {
            this.mediaStore.getByIdAsync(targetId).then((updatedMedia) => {
                this.logoMediaItem = updatedMedia;
            });
            this.shippingMethod.mediaId = targetId;
        },

        setMediaFromSidebar(mediaEntity) {
            this.logoMediaItem = mediaEntity;
            this.shippingMethod.mediaId = mediaEntity.id;
        },

        onUnlinkLogo() {
            this.logoMediaItem = null;
            this.shippingMethod.mediaId = null;
        },

        openMediaSidebar() {
            this.$refs.mediaSidebarItem.openContent();
        }
    }
});
