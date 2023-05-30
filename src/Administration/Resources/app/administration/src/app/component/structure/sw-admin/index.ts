import template from './sw-admin.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-admin', {
    template,

    inject: ['userActivityService', 'loginService'],

    metaInfo() {
        return {
            title: this.$tc('global.sw-admin-menu.textShopwareAdmin'),
        };
    },

    data(): {
        channel: BroadcastChannel | null,
        } {
        return {
            channel: null,
        };
    },

    computed: {
        isLoggedIn() {
            return this.loginService.isLoggedIn();
        },
    },

    created() {
        this.channel = new BroadcastChannel('session_channel');
        this.channel.onmessage = (event) => {
            const data = event.data as { inactive?: boolean };

            if (!data || !Shopware.Utils.object.hasOwnProperty(data, 'inactive')) {
                return;
            }

            const routeBlocklist = ['sw.inactivity.login.index', 'sw.login.index.login'];
            if (!data.inactive || routeBlocklist.includes(this.$router.currentRoute.name || '')) {
                return;
            }

            this.loginService.forwardLogout(true, true);
        };
    },

    beforeDestroy() {
        this.channel?.close();
    },

    methods: {
        onUserActivity() {
            this.userActivityService.updateLastUserActivity();
        },
    },
});
