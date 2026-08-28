/**
 * @sw-package framework
 */

import template from './sw-login.html.twig';
import type { LoginConfig } from '../../../../core/service/login.service';
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

    data(): {
        shouldRenderDOM: boolean;
        isLoading: boolean;
        isLoginSuccess: boolean;
        isLoginError: boolean;
        loginConfig: null | LoginConfig;
    } {
        return {
            shouldRenderDOM: false,
            isLoading: false,
            isLoginSuccess: false,
            isLoginError: false,
            loginConfig: null,
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

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },

        showBackToLoginLink() {
            return !!this.$route?.name && this.$route.name !== 'sw.login.index.login';
        },

        showForgotPasswordLink() {
            return this.$route?.name === 'sw.login.index.login' && !!this.loginConfig?.useDefault;
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

        setLoginConfig(loginConfig: LoginConfig) {
            this.loginConfig = loginConfig;
        },

        loginError() {
            this.isLoginError = !this.isLoginError;
        },

        loginSuccess() {
            this.isLoginSuccess = !this.isLoginSuccess;
        },
    },
});
