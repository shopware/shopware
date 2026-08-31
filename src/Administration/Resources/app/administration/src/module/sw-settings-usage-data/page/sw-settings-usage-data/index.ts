import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import template from './sw-settings-usage-data.html.twig';

const usageDataGeneralRoute = 'sw.settings.usage.data.index.general';

/**
 * @private
 *
 * @sw-package framework
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-usage-data',

    template,

    inject: [
        'feature',
    ],

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
