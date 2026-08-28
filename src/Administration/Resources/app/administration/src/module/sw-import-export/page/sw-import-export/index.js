/**
 * @sw-package fundamentals@after-sales
 */
import template from './sw-import-export.html.twig';

/**
 * @private
 */
export default {
    template,

    inject: ['feature'],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        importExportTabs() {
            const createRouteTab = (label, routeName) => {
                return {
                    label: this.$t(label),
                    name: routeName,
                    onClick: () => {
                        void this.$router.push({ name: routeName });
                    },
                };
            };

            return [
                createRouteTab('sw-import-export.page.importTab', 'sw.import.export.index.import'),
                createRouteTab('sw-import-export.page.exportTab', 'sw.import.export.index.export'),
                createRouteTab('sw-import-export.page.profileTab', 'sw.import.export.index.profiles'),
            ];
        },
    },
};
