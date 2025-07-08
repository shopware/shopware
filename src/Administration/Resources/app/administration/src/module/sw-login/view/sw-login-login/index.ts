/**
 * @sw-package framework
 */

import getErrorCode from 'src/core/data/error-codes/login.error-codes';
import template from './sw-login-login.html.twig';

const { Component, Mixin } = Shopware;

/**
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'loginService',
        'userService',
        'licenseViolationService',
    ],

    emits: [
        'is-loading',
        'is-not-loading',
        'login-success',
        'login-error',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            username: '',
            password: '',
            rememberMe: false,
            loginAlertMessage: '',
        };
    },

    computed: {
        showLoginAlert() {
            return typeof this.loginAlertMessage === 'string' && this.loginAlertMessage.length >= 1;
        },
    },

    created() {
        if (!localStorage.getItem('sw-admin-locale')) {
            void Shopware.Store.get('session').setAdminLocale(navigator.language);
        }
    },

    methods: {
        loginUserWithPassword() {
            this.$emit('is-loading');

            this.loginService.setRememberMe(this.rememberMe);

            return this.loginService
                .loginByUsername(this.username, this.password)
                .then(() => {
                    void this.handleLoginSuccess();
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
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
                this.licenseViolationService.removeTimeFromLocalStorage(this.licenseViolationService.key.showViolationsKey);
            }

            return animationPromise.then(() => {
                // @ts-expect-error
                this.$parent.isLoginSuccess = false;
                this.forwardLogin();

                const shouldReload = sessionStorage.getItem('sw-login-should-reload');

                if (shouldReload) {
                    sessionStorage.removeItem('sw-login-should-reload');
                    // reload page to rebuild the administration with all dependencies
                    // @ts-expect-error - force reload
                    window.location.reload(true);
                }
            });
        },

        forwardLogin() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const previousRoute = JSON.parse(sessionStorage.getItem('sw-admin-previous-route') as string);
            sessionStorage.removeItem('sw-admin-previous-route');

            const firstRunWizard = Shopware.Context.app.firstRunWizard;

            if (
                firstRunWizard &&
                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                !this.$router?.currentRoute?.value?.name?.startsWith('sw.first.run.wizard') &&
                this.$router.hasRoute('sw.first.run.wizard.index')
            ) {
                void this.$router.push({ name: 'sw.first.run.wizard.index' });
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (previousRoute?.fullPath) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-member-access
                void this.$router.push(previousRoute.fullPath);
                return;
            }

            void this.$router.push({ name: 'core' });
        },

        handleLoginError(response: unknown) {
            this.password = '';

            this.$emit('login-error');
            setTimeout(() => {
                this.$emit('login-error');
            }, 500);

            this.createNotificationFromResponse(response);
        },

        createNotificationFromResponse(response: unknown) {
            // @ts-expect-error
            if (!response.response) {
                this.createNotificationError({
                    message: this.$tc('sw-login.index.messageGeneralRequestError'),
                });
                return;
            }

            /* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access */
            // @ts-expect-error
            const url = response.config.url;
            // @ts-expect-error
            let error = response.response.data.errors;
            error = Array.isArray(error) ? error[0] : error;

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            if (parseInt(error.status, 10) === 429) {
                const seconds = error?.meta?.parameters?.seconds;
                this.loginAlertMessage = this.$tc('sw-login.index.messageAuthThrottled', { seconds }, 0);

                setTimeout(() => {
                    this.loginAlertMessage = '';
                }, seconds * 1000);
                return;
            }

            if (error.code?.length) {
                // eslint-disable-next-line max-len
                const { message, title } = getErrorCode(parseInt(error.code as string, 10)) as {
                    message: string;
                    title: string;
                };

                this.createNotificationError({
                    title: this.$tc(title),
                    // @ts-expect-error
                    message: this.$tc(message, 0, { url }),
                });
            }
            /* eslint-enable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access */
        },
    },
});
