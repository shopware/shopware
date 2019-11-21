import template from './sw-plugin-store-login.html.twig';
import './sw-plugin-store-login.scss';

const { Component, State, Mixin } = Shopware;

Component.register('sw-plugin-store-login', {
    template,

    inject: ['storeService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            shopwareId: null,
            password: null
        };
    },

    methods: {
        onLoginClick() {
            State.dispatch(
                'swPlugin/loginShopwareUser', {
                    shopwareId: this.shopwareId,
                    password: this.password
                }
            ).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.store-login.titleLoginSuccess'),
                    message: this.$tc('sw-plugin.store-login.titleLoginMessage')
                });
                this.$emit('close-modal');
            });
        },

        onCloseModal() {
            this.$emit('close-modal');
        }
    }
});
