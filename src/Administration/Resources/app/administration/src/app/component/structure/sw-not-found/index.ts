/**
 * @sw-package framework
 */

import template from './sw-not-found.html.twig';

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
});
