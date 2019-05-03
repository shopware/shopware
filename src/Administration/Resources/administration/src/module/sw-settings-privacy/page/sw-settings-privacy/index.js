import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-privacy.html.twig';

Component.register('sw-settings-privacy', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    methods: {
        onSave() {
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-privacy.general.titleSaveSuccess'),
                    message: this.$tc('sw-settings-privacy.general.messageSaveSuccess')
                });
            }).catch((err) => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-privacy.general.titleSaveError'),
                    message: err
                });
            });
        }
    }
});
