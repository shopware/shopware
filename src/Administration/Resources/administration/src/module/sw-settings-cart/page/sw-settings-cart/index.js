import { Component, Mixin } from 'src/core/shopware';
import template from './sw-settings-cart.html.twig';

Component.register('sw-settings-cart', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],
    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false
        };
    },
    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },
        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.$refs.systemConfig.saveAll().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((err) => {
                this.isLoading = false;
                this.createNotificationError({
                    title: this.$tc('sw-settings-cart.general.titleSaveError'),
                    message: err
                });
            });
        }
    }
});
