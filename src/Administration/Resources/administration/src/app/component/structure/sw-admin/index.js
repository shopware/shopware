import template from './sw-admin.html.twig';

/**
 * @private
 */
export default {
    name: 'sw-admin',
    template,

    metaInfo() {
        return {
            title: this.$tc('global.sw-admin-menu.textShopwareAdmin')
        };
    }
};
