import template from './sw-settings-login-registration.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-settings-login-registration', {
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

    metaInfo() {
        return {
            title: this.$createTitle()
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
                    title: this.$tc('sw-settings-login-registration.general.titleSaveError'),
                    message: err
                });
            });
        }
    }
});
