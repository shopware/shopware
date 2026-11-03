import template from './sw-settings-usage-data.html.twig';
import { featureService, type TabItem, wrapComponentConfig } from '../dependencies';

const usageDataGeneralRoute = 'sw.settings.usage.data.index.general';

/**
 * @private
 *
 * @sw-package framework
 */
export default wrapComponentConfig({
    name: 'sw-settings-usage-data',

    template,

    inject: [featureService],

    computed: {
        usageDataTabs(): TabItem[] {
            return [
                {
                    label: this.$t('sw-settings-usage-data-general.tabHeadline'),
                    name: usageDataGeneralRoute,
                    onClick: () => {
                        void this.$router.push({ name: usageDataGeneralRoute });
                    },
                },
            ];
        },
    },
});
