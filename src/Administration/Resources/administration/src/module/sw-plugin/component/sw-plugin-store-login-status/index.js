import template from './sw-plugin-store-login-status.html.twig';
import './sw-plugin-store-login-status.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-plugin-store-login-status', {
    template,

    inject: ['storeService', 'systemConfigApiService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            shopwareId: null,
            showLoginModal: false,
            isLoggedIn: false
        };
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.load();

            this.$root.$on('plugin-login', this.load);
        },

        destroyedComponent() {
            this.$root.$off('plugin-login', this.load);
        },

        load() {
            this.loadShopwareId();
            this.checkLogin();
        },

        loadShopwareId() {
            this.systemConfigApiService.getValues('core.store').then((response) => {
                const shopwareId = response['core.store.shopwareId'];
                if (shopwareId) {
                    this.shopwareId = shopwareId;
                }
            });
        },

        checkLogin() {
            this.storeService.checkLogin().then((response) => {
                this.isLoggedIn = response.storeTokenExists;
            }).catch(() => {
                this.isLoggedIn = false;
            });
        },

        openAccount() {
            window.open(this.$tc('sw-plugin.general.accountLink'), '_blank');
        },

        login() {
            this.showLoginModal = true;
        },

        logout() {
            this.storeService.logout().then(() => {
                this.shopwareId = null;
                this.load();
                this.$root.$emit('plugin-logout');
            });
        },

        loginSuccess() {
            this.showLoginModal = false;
            this.load();
        },

        loginAbort() {
            this.showLoginModal = false;
        }
    }
});
