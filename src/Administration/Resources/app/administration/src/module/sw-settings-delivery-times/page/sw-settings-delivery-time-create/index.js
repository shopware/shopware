const { Component } = Shopware;

Component.extend('sw-settings-delivery-time-create', 'sw-settings-delivery-time-detail', {
    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.settings.delivery.time.detail', params: { id: this.deliveryTime.id },
            });
        },

        createdComponent() {
            Shopware.State.commit('context/resetLanguageToDefault');

            this.deliveryTime = this.deliveryTimeRepository.create();
        },
    },
});
