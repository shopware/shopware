import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-store.html.twig';

Component.register('sw-settings-store', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },


    methods: {
        onSave() {
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-store.general.titleSaveSuccess'),
                    message: this.$tc('sw-settings-store.general.messageSaveSuccess')
                });
            }).catch((err) => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-store.general.titleSaveError'),
                    message: err
                });
            });
        }
    }
});
