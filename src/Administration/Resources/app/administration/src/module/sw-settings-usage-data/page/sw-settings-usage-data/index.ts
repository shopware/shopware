import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import template from './sw-settings-usage-data.html.twig';

/**
 * @private
 *
 * @sw-package framework
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-usage-data',

    template,

    computed: {
        useMeteorTabs(): boolean {
            return Shopware.Feature.isActive('V6_8_0_0');
        },

        activeTab(): string {
            return this.tabItems.find((item) => item.name === this.$route.name)?.name ?? this.tabItems[0]?.name ?? '';
        },

        tabItems(): TabItem[] {
            return [
                {
                    label: this.$t('sw-settings-usage-data-general.tabHeadline'),
                    name: 'sw.settings.usage.data.index.general',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.settings.usage.data.index.general' });
                    },
                },
            ];
        },
    },
});
