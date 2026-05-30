/**
 * @sw-package framework
 */

import template from './sw-login.html.twig';
import './sw-login.scss';

const { Component } = Shopware;

/**
 * @private
 * @sw-package framework
 */
export default Component.wrapComponentConfig({
    template,

    props: {
        hash: {
            type: String,
            default: null,
        },
    },

    data() {
        return {
            shouldRenderDOM: false,
            isLoading: false,
            isLoginSuccess: false,
            isLoginError: false,
        };
    },

    metaInfo() {
        return {
            title: this.title,
        };
    },

    computed: {
        title() {
            const moduleName = this.$t('sw-login.general.mainMenuItemIndex');
            const adminName = this.$t('global.sw-admin-menu.textShopwareAdmin');

            return `${moduleName} | ${adminName}`;
        },
    },

    beforeMount() {
        const refreshAfterLogout = sessionStorage.getItem('refresh-after-logout');

        if (refreshAfterLogout) {
            sessionStorage.removeItem('refresh-after-logout');
            this._reloadPage();
        } else {
            this.shouldRenderDOM = true;
        }
    },

    methods: {
        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _reloadPage() {
            window.location.reload();
        },

        setLoading(val: boolean) {
            this.isLoading = val;
        },

        loginError() {
            this.isLoginError = !this.isLoginError;
        },

        loginSuccess() {
            this.isLoginSuccess = !this.isLoginSuccess;
        },
    },
});
