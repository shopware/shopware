import template from './sw-admin.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-admin', {
    template,

    inject: ['userActivityService'],

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

    methods: {
        onUserActivity: Shopware.Utils.debounce(function updateUserActivity() {
            this.userActivityService.updateLastUserActivity();
        }, 5000),
    },
});
