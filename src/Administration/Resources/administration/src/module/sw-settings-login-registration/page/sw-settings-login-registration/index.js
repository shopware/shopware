import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-login-registration.html.twig';

Component.register('sw-settings-login-registration', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    methods: {
        onSave() {
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-login-registration.general.titleSaveSuccess'),
                    message: this.$tc('sw-settings-login-registration.general.messageSaveSuccess')
                });
            }).catch((err) => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-login-registration.general.titleSaveError'),
                    message: err
                });
            });
        }
    }
});
