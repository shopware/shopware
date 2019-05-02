import getErrorCode from 'src/core/data/error-codes/login.error-codes';
import { Component, Mixin, State, Application } from 'src/core/shopware';
import { initializeUserNotifications } from 'src/app/state/notification.store';
import template from './sw-login-login.html.twig';

Component.register('sw-login-login', {
    template,

    inject: ['loginService', 'userService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            username: '',
            password: ''
        };
    },

    methods: {
        loginUserWithPassword() {
            this.$emit('is-loading');

            return this.loginService.loginByUsername(this.username, this.password)
                .then(() => {
                    this.handleLoginSuccess();
                    this.$emit('is-not-loading');
                })
                .catch((response) => {
                    this.password = '';

                    this.handleLoginError(response);
                    this.$emit('is-not-loading');
                });
        },

        handleLoginSuccess() {
            const contextService = Application.getContainer('init');
            this.password = '';

            this.$emit('login-success');

            const animationPromise = new Promise((resolve) => {
                setTimeout(resolve, 300);
            });

            // Fetch the user info when the login was successful and save it in current context
            const userInfoPromise = this.userService.getUser().then((response) => {
                const data = response.data;
                delete data.password;

                contextService.currentUser = data;
                this.$store.commit('adminUser/setCurrentUser', data);
                initializeUserNotifications();
            });

            return Promise.all([
                animationPromise,
                userInfoPromise,
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

            this.$emit('login-error');
            setTimeout(() => {
                this.$emit('login-error');
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
