import template from './sw-settings-store.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-settings-store', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],
    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    saveFinish() {
        this.isSaveSuccessful = false;
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },


    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.trimHost();

            this.$refs.systemConfig.saveAll().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((err) => {
                this.isLoading = false;
                this.createNotificationError({
                    message: err,
                });
            });
        },

        trimHost() {
            const actualConfigData = this.$refs.systemConfig.actualConfigData;

            if (actualConfigData.null?.['core.store.licenseHost']) {
                actualConfigData.null['core.store.licenseHost'] = actualConfigData.null['core.store.licenseHost'].trim();
            }
        },
    },
});
