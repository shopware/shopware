import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-login.html.twig';
import './sw-login.less';

Component.register('sw-login', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isLoginSuccess: false,
            isLoginError: false
        };
    },

    computed: {
        authStore() {
            return State.getStore('auth');
        }
    },

    methods: {
        loginUserWithPassword() {
            this.isLoading = true;

            return this.authStore.loginUserWithPassword().then((success) => {
                this.isLoading = false;

                if (success === true) {
                    this.handleLoginSuccess();
                } else {
                    this.handleLoginError();
                }
            });
        },

        handleLoginSuccess() {
            this.isLoginSuccess = true;

            setTimeout(() => {
                this.isLoginSuccess = false;
                this.forwardLogin();
            }, 300);
        },

        handleLoginError() {
            this.isLoginError = true;

            setTimeout(() => {
                this.isLoginError = false;
            }, 500);

            this.createNotificationError({
                title: this.authStore.errorTitle,
                message: this.authStore.errorMessage
            });
        },

        forwardLogin() {
            const previousRoute = JSON.parse(sessionStorage.getItem('sw-admin-previous-route'));
            sessionStorage.removeItem('sw-admin-previous-route');

            if (!this.authStore.token.length || this.authStore.expiry === -1) {
                return;
            }

            if (previousRoute.name && previousRoute.fullPath) {
                this.$router.push(previousRoute.fullPath);
                return;
            }

            this.$router.push({ name: 'core' });
        }
    }
});
