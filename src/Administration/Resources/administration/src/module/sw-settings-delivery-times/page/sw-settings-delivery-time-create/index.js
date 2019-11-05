const { Component, StateDeprecated } = Shopware;

Component.extend('sw-settings-delivery-time-create', 'sw-settings-delivery-time-detail', {
    computed: {
        languageStore() {
            return StateDeprecated.getStore('language');
        }
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.settings.delivery.time.detail', params: { id: this.deliveryTime.id }
            });
        },

        createdComponent() {
            this.languageStore.setCurrentId(this.languageStore.systemLanguageId);

            this.deliveryTime = this.deliveryTimeRepository.create(Shopware.Context.api);
        }
    }
});
