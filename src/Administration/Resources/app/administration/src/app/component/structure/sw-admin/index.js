import template from './sw-admin.html.twig';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-admin', {
    template,

    metaInfo() {
        return {
            title: this.$tc('global.sw-admin-menu.textShopwareAdmin'),
        };
    },

    computed: {
        isLoggedIn() {
            return Shopware.Service('loginService').isLoggedIn();
        },
    },
});
