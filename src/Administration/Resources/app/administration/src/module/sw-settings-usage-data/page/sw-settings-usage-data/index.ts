import template from './sw-settings-usage-data.html.twig';
import './sw-settings-usage-data.scss';

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

    computed: {
        alertText(): string {
            let alertText = this.$tc('sw-settings-usage-data.general.alertText');

            if (!this.isAdmin) {
                alertText += ` ${this.$tc('sw-settings-usage-data.general.alertTextOnlyAdmins')}`;
            }

            return alertText;
        },

        isAdmin(): boolean {
            return this.acl.isAdmin();
        },
    },

    created() {
        void this.createdComponent();
    },
});
