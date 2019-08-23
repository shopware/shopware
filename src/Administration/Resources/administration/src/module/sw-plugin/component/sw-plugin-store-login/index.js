import template from './sw-plugin-store-login.html.twig';
import './sw-plugin-store-login.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-plugin-store-login', {
    template,

    inject: ['storeService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('plugin-error-handler')
    ],

    data() {
        return {
            shopwareId: null,
            password: null
        };
    },

    methods: {
        onLoginClick() {
            this.storeService.login(this.shopwareId, this.password).then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin.store-login.titleLoginSuccess'),
                    message: this.$tc('sw-plugin.store-login.titleLoginMessage')
                });
                this.$emit('login-success');
            }).catch((exception) => {
                this.handleErrorResponse(exception);
            });
        },

        onCloseModal() {
            this.$emit('login-abort');
        }
    }
});
