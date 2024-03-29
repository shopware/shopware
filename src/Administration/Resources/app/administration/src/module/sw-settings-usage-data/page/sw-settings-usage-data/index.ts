import template from './sw-settings-usage-data.html.twig';

/**
 * @private
 *
 * @package services-settings
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-usage-data',
    template,

    inject: [
        'usageDataService',
    ],

    methods: {
        async createdComponent() {
            const consent = await this.usageDataService.getConsent();

            Shopware.State.commit('usageData/updateConsent', consent);
        },
    },

    created() {
        void this.createdComponent();
    },
});
