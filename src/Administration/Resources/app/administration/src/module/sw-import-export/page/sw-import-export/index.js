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
        Shopware() {
            return Shopware;
        },

        defaultTabItem() {
            if (this.$route?.name === 'sw.import.export.index.export') {
                return 'export';
            }

            if (this.$route?.name === 'sw.import.export.index.profiles') {
                return 'profiles';
            }

            return 'import';
        },

        tabItems() {
            return [
                {
                    label: this.$t('sw-import-export.page.importTab'),
                    name: 'import',
                    route: { name: 'sw.import.export.index.import' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.import.export.index.import' });
                    },
                },
                {
                    label: this.$t('sw-import-export.page.exportTab'),
                    name: 'export',
                    route: { name: 'sw.import.export.index.export' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.import.export.index.export' });
                    },
                },
                {
                    label: this.$t('sw-import-export.page.profileTab'),
                    name: 'profiles',
                    route: { name: 'sw.import.export.index.profiles' },
                    onClick: () => {
                        this.$router.push({ name: 'sw.import.export.index.profiles' });
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
