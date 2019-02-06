import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-settings-shipping-detail.html.twig';
import './sw-settings-shipping-detail.scss';

Component.register('sw-settings-shipping-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('shippingMethod')
    ],

    data() {
        return {
            shippingMethod: {}
        };
    },

    computed: {
        shippingMethodStore() {
            return State.getStore('shipping_method');
        },
        shippingMethodRuleAssociation() {
            return this.shippingMethod.getAssociation('availabilityRules');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.shippingMethodId = this.$route.params.id;
                this.shippingMethod = this.shippingMethodStore.getById(this.shippingMethodId);
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
