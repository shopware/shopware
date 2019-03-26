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
            showLoginModal: false
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
            this.loadShopwareId();

            this.$root.$on('sw-plugin-login', this.loadShopwareId);
        },

        destroyedComponent() {
            this.$root.$off('sw-plugin-login', this.loadShopwareId);
        },

        loadShopwareId() {
            this.storeSettingsStore.getList().then((response) => {
                const settings = response.items.filter(setting => setting.key === 'shopwareId');
                if (settings.length === 1) {
                    this.shopwareId = settings[0].value;
                }
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
                this.loadShopwareId();
            });
        },

        loginSuccess() {
            this.showLoginModal = false;
            this.loadShopwareId();
        },

        loginAbort() {
            this.showLoginModal = false;
        }
    }
});
