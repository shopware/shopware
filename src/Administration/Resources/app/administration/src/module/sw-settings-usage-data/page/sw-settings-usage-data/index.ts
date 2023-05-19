import { METRICS_SYSTEM_CONFIG_DOMAIN, ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY } from 'src/core/service/api/metrics.api.service';
import template from './sw-settings-usage-data.html.twig';
import './sw-settings-usage-data.scss';

type CoreMetricsConfigNamespace = {
    [ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY]?: boolean
}

/**
 * @private
 *
 * @package merchant-services
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'acl',
        'systemConfigApiService',
    ],

    data(): { shareUsageData: boolean } {
        return {
            shareUsageData: false,
        };
    },

    computed: {
        alertText() {
            let alertText = this.$tc('sw-settings-usage-data.general.alertText');

            if (!this.isAdmin) {
                alertText += ` ${this.$tc('sw-settings-usage-data.general.alertTextOnlyAdmins')}`;
            }

            return alertText;
        },

        isAdmin() {
            return this.acl.isAdmin();
        },
    },

    created() {
        void this.createdComponent();
    },

    methods: {
        async createdComponent(): Promise<void> {
            const config = await this.systemConfigApiService.getValues(
                METRICS_SYSTEM_CONFIG_DOMAIN,
            ) as CoreMetricsConfigNamespace;

            this.shareUsageData = config[ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY] ?? false;
        },

        async saveSystemConfig() {
            await this.systemConfigApiService.saveValues({
                [ALLOW_USAGE_DATA_SYSTEM_CONFIG_KEY]: this.shareUsageData,
            });
        },
    },
});
