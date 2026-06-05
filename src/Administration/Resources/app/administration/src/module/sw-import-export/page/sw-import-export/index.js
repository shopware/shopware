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
        tabs() {
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
