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

    mounted() {
        this.mountedComponent();
    },

    computed: {
        authStore() {
            return State.getStore('auth');
        }
    },

    methods: {
        mountedComponent() {
            const usernameField = this.$refs.swLoginUsernameField.$el.querySelector('input');

            usernameField.focus();
        },

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
