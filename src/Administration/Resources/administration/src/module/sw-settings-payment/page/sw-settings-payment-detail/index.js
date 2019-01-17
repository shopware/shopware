import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-payment-detail.html.twig';

Component.register('sw-settings-payment-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
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

            return this.paymentMethod.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
