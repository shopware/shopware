import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-basic-information.html.twig';

Component.register('sw-settings-basic-information', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    methods: {
        onSave() {
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-basic-information.general.titleSaveSuccess'),
                    message: this.$tc('sw-settings-basic-information.general.messageSaveSuccess')
                });
            }).catch((err) => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-basic-information.general.titleSaveError'),
                    message: err
                });
            });
        }
    }
});
