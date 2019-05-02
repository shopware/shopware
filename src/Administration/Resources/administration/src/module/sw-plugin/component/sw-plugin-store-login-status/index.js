import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-plugin-store-login-status.html.twig';
import './sw-plugin-store-login-status.scss';

Component.register('sw-plugin-store-login-status', {
    template,

    inject: ['storeService'],

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

    computed: {
        storeSettingsStore() {
            return State.getStore('store_settings');
        }
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

            this.$root.$on('sw-plugin-login', this.load);
        },

        destroyedComponent() {
            this.$root.$off('sw-plugin-login', this.load);
        },

        load() {
            this.loadShopwareId();
            this.checkLogin();
        },

        loadShopwareId() {
            this.storeSettingsStore.getList().then((response) => {
                const settings = response.items.filter(setting => setting.key === 'shopwareId');
                if (settings.length === 1) {
                    this.shopwareId = settings[0].value;
                }
            });
        },

        checkLogin() {
            this.storeService.checkLogin().then(() => {
                this.isLoggedIn = true;
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
                this.$root.$emit('sw-plugin-logout');
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
