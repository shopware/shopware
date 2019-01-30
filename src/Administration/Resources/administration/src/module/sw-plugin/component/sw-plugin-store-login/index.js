import { Component, Mixin } from 'src/core/shopware';
import template from './sw-plugin-store-login.twig';

Component.register('sw-plugin-store-login', {
    name: 'sw-plugin-store-login',
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

    computed: {
    },

    methods: {
        onLoginClick() {
            console.log(this.shopwareId);
            console.log(this.password);
            this.storeService.login(this.shopwareId, this.password).then(() => {
                this.createNotificationSuccess({
                    title: 'Shopware Account',
                    message: 'Login erfolgreich'
                });
                this.$emit('sw-plugin-store-login-success');
            });
        }
    }
});
