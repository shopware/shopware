/**
 * @sw-package framework
 */

import template from './sw-not-found.html.twig';
import './sw-not-found.scss';

/**
 * @private - Only to be used by the Shopware Admin
 * @description Renders the 404 page for URLs that do not match any route.
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

    methods: {
        goBack() {
            // Without a previous Administration page, e.g. on a direct visit, going back would leave the Administration
            if (!this.$router.options.history.state.back) {
                void this.$router.push({ name: 'sw.dashboard.index' });
                return;
            }

            this.$router.go(-1);
        },
    },
});
