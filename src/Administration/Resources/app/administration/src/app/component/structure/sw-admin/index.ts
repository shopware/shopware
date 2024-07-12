import type { Toast } from '@shopware-ag/meteor-component-library/dist/esm/components/feedback-indicator/mt-toast/mt-toast';
import template from './sw-admin.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-admin', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['userActivityService', 'loginService', 'feature'],

    metaInfo() {
        return {
            title: this.$tc('global.sw-admin-menu.textShopwareAdmin'),
        };
    },

    data(): {
        channel: BroadcastChannel | null,
        toasts: Toast[],
        } {
        return {
            channel: null,
            toasts: [],
        };
    },

    computed: {
        isLoggedIn() {
            return this.loginService.isLoggedIn();
        },
    },

    created() {
        Shopware.ExtensionAPI.handle('toastDispatch', (toast) => {
            this.toasts = [
                {
                    id: Shopware.Utils.createId(),
                    ...toast,
                },
                ...this.toasts,
            ];
        });

        this.channel = new BroadcastChannel('session_channel');
        this.channel.onmessage = (event) => {
            const data = event.data as { inactive?: boolean };

            if (!data || !Shopware.Utils.object.hasOwnProperty(data, 'inactive')) {
                return;
            }

            // eslint-disable-next-line max-len,@typescript-eslint/no-unsafe-member-access
            const currentRouteName = (this.$router.currentRoute.value.name) as string;
            const routeBlocklist = ['sw.inactivity.login.index', 'sw.login.index.login'];
            if (!data.inactive || routeBlocklist.includes(currentRouteName || '')) {
                return;
            }

            this.loginService.forwardLogout(true, true);
        };
    },

    beforeUnmount() {
        this.channel?.close();
    },

    methods: {
        onUserActivity() {
            this.userActivityService.updateLastUserActivity();
        },

        onRemoveToast(id: number) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    },
});
