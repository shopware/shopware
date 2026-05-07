/**
 * @sw-package fundamentals@after-sales
 */
import template from './sw-import-export.html.twig';

/**
 * @private
 */
export default {
    template,

    computed: {
        useMeteorTabs() {
            return Shopware.Feature.isActive('V6_8_0_0');
        },

        activeTab() {
            return this.tabItems.find((item) => item.name === this.$route.name)?.name ?? this.tabItems[0]?.name;
        },

        tabItems() {
            return [
                {
                    label: this.$t('sw-import-export.page.importTab'),
                    name: 'sw.import.export.index.import',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.import.export.index.import' });
                    },
                },
                {
                    label: this.$t('sw-import-export.page.exportTab'),
                    name: 'sw.import.export.index.export',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.import.export.index.export' });
                    },
                },
                {
                    label: this.$t('sw-import-export.page.profileTab'),
                    name: 'sw.import.export.index.profiles',
                    onClick: () => {
                        void this.$router.push({ name: 'sw.import.export.index.profiles' });
                    },
                },
            ];
        },
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },
};
