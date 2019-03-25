import { Component, Mixin } from 'src/core/shopware';
import template from './sw-plugin-store-login.html.twig';
import './sw-plugin-store-login.scss';

Component.register('sw-plugin-store-login', {
    template,

    inject: ['storeService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            shopwareId: '',
            password: ''
        };
    },

    methods: {
        onLoginClick() {
            this.storeService.login(this.shopwareId, this.password).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.store-login.titleLoginSuccess'),
                    message: this.$tc('sw-plugin.store-login.titleLoginMessage')
                });
                this.$emit('sw-plugin-store-login-success');
            });
        },

        onCloseModal() {
            this.$emit('sw-plugin-store-login-abort');
        }
    }
});
