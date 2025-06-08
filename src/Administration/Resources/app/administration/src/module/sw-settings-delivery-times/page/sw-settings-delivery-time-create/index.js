/**
 * @sw-package discovery
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({
                name: 'sw.settings.delivery.time.detail',
                params: { id: this.deliveryTime.id },
            });
        },

        createdComponent() {
            Shopware.Store.get('context').resetLanguageToDefault();

            this.deliveryTime = this.deliveryTimeRepository.create();
        },
    },
};
