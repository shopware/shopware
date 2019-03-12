import { Component, State, Mixin } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-settings-payment-detail.html.twig';

Component.register('sw-settings-payment-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('discard-detail-page-changes')('paymentMethod')
    ],

    data() {
        return {
            paymentMethod: {}
        };
    },

    computed: {
        paymentMethodStore() {
            return State.getStore('payment_method');
        },
        ruleStore() {
            return State.getStore('rule');
        },
        paymentMethodRuleAssociation() {
            return this.paymentMethod.getAssociation('availabilityRules');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.paymentMethodId = this.$route.params.id;
                this.paymentMethod = this.paymentMethodStore.getById(this.paymentMethodId);
            }
        },

        onSave() {
            const paymentMethodName = this.paymentMethod.name;
            const titleSaveSuccess = this.$tc('sw-settings-payment.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-settings-payment.detail.messageSaveSuccess', 0, {
                name: paymentMethodName
            });

            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: paymentMethodName }
            );

            return this.paymentMethod.save().then(() => {
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
