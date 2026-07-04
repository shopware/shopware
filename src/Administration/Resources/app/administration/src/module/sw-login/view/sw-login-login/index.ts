/**
 * @sw-package framework
 */

import getErrorCode from 'src/core/data/error-codes/login.error-codes';
import { isWebAuthnSupported } from 'src/core/helper/webauthn.helper';
import type { AdminAuthMethod } from 'src/core/service/login.service';
import template from './sw-login-login.html.twig';

const { Component, Mixin } = Shopware;

interface LoginData {
    username: string;
    password: string;
    rememberMe: boolean;
    loginAlertMessage: string;
    authMethods: AdminAuthMethod[];
    authMethodsLoaded: boolean;
    mfaRequired: boolean;
    mfaMethods: string[];
}

const SSO_ERROR_SNIPPETS: Record<string, string> = {
    'mfa-required': 'sw-login.index.ssoErrorMfaRequired',
    'invalid-state': 'sw-login.index.ssoErrorInvalidState',
};

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

    data(): LoginData {
        return {
            username: '',
            password: '',
            rememberMe: false,
            loginAlertMessage: '',
            authMethods: [],
            authMethodsLoaded: false,
            mfaRequired: false,
            mfaMethods: [],
        };
    },

    computed: {
        showLoginAlert() {
            return this.loginAlertMessage?.length >= 1;
        },

        adminAuthEnabled() {
            return Shopware.Feature.isActive('ADMIN_AUTH');
        },

        oidcMethods(): AdminAuthMethod[] {
            return this.authMethods.filter((method) => method.type === 'oidc' && !!method.startUrl);
        },

        showPasskeyLogin(): boolean {
            return this.authMethods.some((method) => method.type === 'webauthn') && isWebAuthnSupported();
        },

        showAlternativeLogins(): boolean {
            return this.adminAuthEnabled && (this.oidcMethods.length > 0 || this.showPasskeyLogin);
        },
    },

    created(): void {
        void this.createdComponent();
    },

    methods: {
        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _reloadPage() {
            window.location.reload();
        },

        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _navigateTo(url: string) {
            window.location.href = url;
        },

        async createdComponent() {
            if (!localStorage.getItem('sw-admin-locale')) {
                const localeFactory = Shopware.Application.getContainer('factory').locale;

                await Shopware.Store.get('session').setAdminLocale(localeFactory.getLastKnownLocale());
            }

            if (this.adminAuthEnabled) {
                this.checkSsoError();
                await this.loadAuthMethods();
            }
        },

        /**
         * Load the available primary login methods. Failures are swallowed so the plain password
         * form keeps working even when the discovery endpoint is unreachable.
         */
        async loadAuthMethods() {
            try {
                this.authMethods = await this.loginService.getAvailableAuthMethods();
            } catch {
                this.authMethods = [];
            } finally {
                this.authMethodsLoaded = true;
            }
        },

        /**
         * Surface a failed server-side SSO round-trip (`?ssoError=<code>` set by the OIDC callback
         * redirect) as a notification, once, on entering the login screen.
         */
        checkSsoError() {
            const ssoError = this.$route?.query?.ssoError;

            if (typeof ssoError !== 'string' || ssoError.length === 0) {
                return;
            }

            const snippetKey = SSO_ERROR_SNIPPETS[ssoError] ?? 'sw-login.index.ssoErrorGeneric';

            this.createNotificationError({
                message: this.$t(snippetKey),
            });
        },

        startOidcLogin(method: AdminAuthMethod) {
            if (method.startUrl) {
                this._navigateTo(method.startUrl);
            }
        },

        loginUserWithPassword() {
            if (this.mfaRequired) {
                return Promise.resolve();
            }

            this.$emit('is-loading');

            this.loginService.setRememberMe(this.rememberMe);

            if (!this.adminAuthEnabled) {
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
            }

            return this.loginService
                .loginPrimary(this.username, this.password)
                .then((result) => {
                    if (result.mfaRequired) {
                        this.enterMfaStep(result.methods);
                        this.$emit('is-not-loading');
                        return;
                    }

                    void this.handleLoginSuccess();
                    this.$emit('is-not-loading');
                })
                .catch((response) => {
                    this.password = '';

                    this.handleLoginError(response);
                    this.$emit('is-not-loading');
                });
        },

        /**
         * Passwordless login with a passkey. Mirrors the success/MFA branching of the password
         * submit so it can never get stuck on a half-authenticated state.
         */
        loginWithPasskey() {
            this.$emit('is-loading');

            this.loginService.setRememberMe(this.rememberMe);

            return this.loginService
                .loginWithPasskey()
                .then((result) => {
                    if (result.mfaRequired) {
                        this.enterMfaStep(result.methods);
                        this.$emit('is-not-loading');
                        return;
                    }

                    void this.handleLoginSuccess();
                    this.$emit('is-not-loading');
                })
                .catch((error: unknown) => {
                    this.createNotificationError({
                        message: this.extractPasskeyErrorMessage(error),
                    });
                    this.$emit('is-not-loading');
                });
        },

        extractPasskeyErrorMessage(error: unknown): string {
            // Server-side OAuth errors are wrapped in a response; surface their detail when present.
            /* eslint-disable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access */
            const errors = (error as { response?: { data?: { errors?: unknown } } })?.response?.data?.errors;

            if (Array.isArray(errors) && errors.length > 0) {
                const first = errors[0] as { detail?: string; title?: string };

                if (first.detail || first.title) {
                    return first.detail ?? first.title!;
                }
            }
            /* eslint-enable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access */

            // Ceremony / client-side errors carry a user-facing message (e.g. "cancelled").
            if (!(error as { response?: unknown })?.response && error instanceof Error && error.message) {
                return error.message;
            }

            return this.$t('sw-login.index.passkeyError');
        },

        enterMfaStep(methods: string[]) {
            this.mfaRequired = true;
            this.mfaMethods = methods;
            // Keep the password out of memory while we are on the second-factor step.
            this.password = '';
        },

        onMfaSuccess() {
            this.mfaRequired = false;
            this.mfaMethods = [];

            void this.handleLoginSuccess();
        },

        onMfaCancel() {
            this.loginService.clearPendingMfa();

            this.mfaRequired = false;
            this.mfaMethods = [];
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

            return animationPromise.then(async () => {
                // @ts-expect-error
                this.$parent.isLoginSuccess = false;
                await this.forwardLogin();

                const shouldReload = sessionStorage.getItem('sw-login-should-reload');

                if (shouldReload) {
                    sessionStorage.removeItem('sw-login-should-reload');
                    // reload page to rebuild the administration with all dependencies
                    this._reloadPage();
                }
            });
        },

        async forwardLogin() {
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
                void (await this.$router.push({ name: 'sw.first.run.wizard.index' }));
                return;
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            if (previousRoute?.fullPath) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-member-access
                void (await this.$router.push(previousRoute.fullPath));
                return;
            }

            void (await this.$router.push({ name: 'core' }));
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
                    message: this.$t('sw-login.index.messageGeneralRequestError'),
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
                this.loginAlertMessage = this.$t('sw-login.index.messageAuthThrottled', { seconds }, 0);

                setTimeout(() => {
                    this.loginAlertMessage = '';
                }, seconds * 1000);
                return;
            }

            if (error.code?.length) {
                const { message, title } = getErrorCode(parseInt(error.code as string, 10)) as {
                    message: string;
                    title: string;
                };

                this.createNotificationError({
                    title: this.$t(title),
                    // @ts-expect-error
                    message: this.$t(message, 0, { url }),
                });
            }
            /* eslint-enable @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access */
        },
    },
});
