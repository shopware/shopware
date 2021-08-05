import template from './sw-login.html.twig';
import './sw-login.scss';

const { Component } = Shopware;

Component.register('sw-login', {
    template,

    props: {
        hash: {
            type: String,
            default: null,
        },
    },

    data() {
        return {
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
            const modulName = this.$tc('sw-login.general.mainMenuItemIndex');
            const adminName = this.$tc('global.sw-admin-menu.textShopwareAdmin');

            return `${modulName} | ${adminName}`;
        },
    },

    methods: {
        setLoading(val) {
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
