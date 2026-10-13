/**
 * @sw-package framework
 */

import template from './sw-not-found.html.twig';

/**
 * @private - Only to be used by the Shopware Admin
 * @description Renders the 404 page for URLs that do not match any route.
 * Shares its styles with the server-side 404 page (`error-404.html.twig`).
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    metaInfo() {
        return {
            title: [
                this.$t('sw-not-found.title'),
                this.$t('global.sw-admin-menu.textShopwareAdmin'),
            ].join(' | '),
        };
    },

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },

    methods: {
        goToDashboard() {
            void this.$router.push({ name: 'sw.dashboard.index' });
        },
    },
});
