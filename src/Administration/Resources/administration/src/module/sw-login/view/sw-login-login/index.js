import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-login-login.html.twig';

Component.register('sw-login-login', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            loginError: false
        };
    },

    computed: {
        authStore() {
            return State.getStore('auth');
        }
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            const usernameField = this.$refs.swLoginUsernameField.$el.querySelector('input');

            usernameField.focus();
        },

        loginUserWithPassword() {
            this.$emit('isLoading');

            return this.authStore.loginUserWithPassword().then((success) => {
                this.$emit('isNotLoading');

                if (success === true) {
                    this.handleLoginSuccess();
                } else {
                    this.handleLoginError();
                }
            });
        },

        handleLoginSuccess() {
            this.$emit('loginSuccess');
            this.loginError = false;

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

        handleLoginError() {
            // Since the AuthStore is not reactive, this fix is necessary.
            this.$refs.swLoginPasswordField.currentValue = '';

            this.$emit('loginError');
            this.loginError = true;

            setTimeout(() => {
                this.$emit('loginError');
            }, 500);

            this.createNotificationError({
                title: this.$tc(this.authStore.errorTitle),
                message: this.$tc(this.authStore.errorMessage, 0, { url: this.authStore.lastUrl })
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
        }
    }
});
