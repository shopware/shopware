import getErrorCode from 'src/core/data/error-codes/login.error-codes';
import template from './sw-login-login.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-login-login', {
    template,

    inject: ['loginService', 'userService', 'licenseViolationService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            username: '',
            password: '',
        };
    },

    created() {
        if (!localStorage.getItem('sw-admin-locale')) {
            Shopware.State.dispatch('setAdminLocale', navigator.language);
        }
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
            this.password = '';

            this.$emit('login-success');

            const animationPromise = new Promise((resolve) => {
                setTimeout(resolve, 150);
            });

            if (this.licenseViolationService) {
                this.licenseViolationService.removeTimeFromLocalStorage(this.licenseViolationService.key.showViolationsKey);
            }

            return animationPromise.then(() => {
                this.$parent.isLoginSuccess = false;
                this.forwardLogin();

                const shouldReload = sessionStorage.getItem('sw-login-should-reload');

                if (shouldReload) {
                    sessionStorage.removeItem('sw-login-should-reload');
                    // reload page to rebuild the administration with all dependencies
                    this.$router.go();
                }
            });
        },

        forwardLogin() {
            const previousRoute = JSON.parse(sessionStorage.getItem('sw-admin-previous-route'));
            sessionStorage.removeItem('sw-admin-previous-route');

            const firstRunWizard = Shopware.Context.app.firstRunWizard;

            if (firstRunWizard && !this.$router.history.current.name.startsWith('sw.first.run.wizard.')) {
                this.$router.push({ name: 'sw.first.run.wizard.index' });
                return;
            }

            if (previousRoute?.fullPath) {
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
                    message: this.$tc('sw-login.index.messageGeneralRequestError'),
                });
                return;
            }

            const url = response.config.url;
            let error = response.response.data.errors;
            error = error.length > 1 ? error : error[0];

            if (error.code?.length) {
                const { message, title } = getErrorCode(parseInt(error.code, 10));

                this.createNotificationError({
                    title: this.$tc(title),
                    message: this.$tc(message, 0, { url }),
                });
            }
        },
    },
});
