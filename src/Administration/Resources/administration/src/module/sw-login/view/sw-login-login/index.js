import getErrorCode from 'src/core/data/error-codes/login.error-codes';
import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-login-login.html.twig';

Component.register('sw-login-login', {
    template,

    inject: ['loginService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            username: '',
            password: '',
            lastUrl: ''
        };
    },

    methods: {
        loginUserWithPassword() {
            this.$emit('isLoading');

            return this.loginService.loginByUsername(this.username, this.password)
                .then(() => {
                    this.handleLoginSuccess();
                    this.$emit('isNotLoading');
                })
                .catch((response) => {
                    this.password = '';

                    this.handleLoginError(response);
                    this.$emit('isNotLoading');
                });
        },

        handleLoginSuccess() {
            this.lastUrl = '';
            this.password = '';

            this.$emit('loginSuccess');

            const animationPromise = new Promise((resolve) => {
                setTimeout(resolve, 300);
            });
            return Promise.all([
                animationPromise,
                State.getStore('language').init()
            ]).then(() => {
                this.$parent.isLoginSuccess = false;
                this.forwardLogin();
            });
        },

        forwardLogin() {
            const previousRoute = JSON.parse(sessionStorage.getItem('sw-admin-previous-route'));
            sessionStorage.removeItem('sw-admin-previous-route');

            if (previousRoute && previousRoute.name && previousRoute.fullPath) {
                this.$router.push(previousRoute.fullPath);
                return;
            }

            this.$router.push({ name: 'core' });
        },

        handleLoginError(response) {
            this.password = '';

            this.$emit('loginError');
            setTimeout(() => {
                this.$emit('loginError');
            }, 500);

            this.createNotificationFromResponse(response);
        },

        createNotificationFromResponse(response) {
            if (!response.response) {
                this.createNotificationError({
                    title: response.message,
                    message: this.$tc('sw-login.index.messageGeneralRequestError')
                });
                return;
            }

            const url = response.config.url;
            let error = response.response.data.errors;
            error = error.length > 1 ? error : error[0];

            if (error.code && error.code.length) {
                const { message, title } = getErrorCode(parseInt(error.code, 10));

                this.createNotificationError({
                    title: this.$tc(title),
                    message: this.$tc(message, 0, { url })
                });
            }
        }
    }
});
