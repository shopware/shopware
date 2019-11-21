import template from './sw-plugin-store-login-status.html.twig';
import './sw-plugin-store-login-status.scss';

const { Component, State, Mixin } = Shopware;

const systemConfigApiService = Shopware.Service('systemConfigApiService');

Component.register('sw-plugin-store-login-status', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            showLoginModal: false
        };
    },

    computed: {
        shopwareId: {
            get() { return State.get('swPlugin').shopwareId; },
            set(shopwareId) { State.dispatch('swPlugin/storeShopwareId', shopwareId); }
        },

        isLoggedIn() {
            return State.get('swPlugin').loginStatus;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            return systemConfigApiService.getValues('core.store')
                .then((response) => {
                    this.shopwareId = response['core.store.shopwareId'] || null;
                }).then(() => {
                    State.dispatch('swPlugin/checkLogin');
                });
        },

        openAccount() {
            window.open(this.$tc('sw-plugin.general.accountLink'), '_blank');
        },

        login() {
            this.showLoginModal = true;
        },

        logout() {
            State.dispatch('swPlugin/logoutShopwareUser');
        },

        closeModal() {
            this.showLoginModal = false;
        }
    }
});
