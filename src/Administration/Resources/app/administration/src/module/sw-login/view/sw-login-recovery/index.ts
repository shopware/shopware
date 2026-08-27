/**
 * @sw-package framework
 */

import template from './sw-login-recovery.html.twig';

const { Component } = Shopware;
const { debounce } = Shopware.Utils;

/**
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    emits: [
        'is-loading',
        'is-not-loading',
    ],

    inject: [
        'validationApiService',
    ],

    data(): {
        email: string;
        isEmailValid: boolean;
        rateLimitMessage: string;
        rateLimitTimeout: null | ReturnType<typeof setTimeout>;
    } {
        return {
            email: '',
            isEmailValid: false,
            rateLimitMessage: '',
            rateLimitTimeout: null,
        };
    },

    computed: {
        isRateLimited() {
            return this.rateLimitMessage.length >= 1;
        },

        showLinkExpiredError() {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            return window.history.state?.linkExpired === true && !this.isRateLimited;
        },
    },

    mounted() {
        this.mountedComponent();
    },

    beforeUnmount() {
        if (this.rateLimitTimeout) {
            clearTimeout(this.rateLimitTimeout);
        }
    },

    methods: {
        mountedComponent() {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
            const emailField = this.$refs.swLoginRecoveryEmailField.$el.querySelector('input') as HTMLInputElement;

            emailField.focus();
        },

        async checkEmailIsValid() {
            return this.validationApiService
                .validateEmailAddress(this.email)
                .then((isValid) => {
                    this.isEmailValid = isValid;
                })
                .catch((error: unknown) => {
                    this.handleRateLimitError(error);
                });
        },

        debouncedEmailValidation: debounce(function test() {
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            this.checkEmailIsValid();
        }, 500),

        sendRecoveryMail() {
            this.$emit('is-loading');

            Shopware.Service('userRecoveryService')
                .createRecovery(this.email)
                .then(() => {
                    this.displayRecoveryInfo();
                })
                .catch((error: unknown) => {
                    if (this.handleRateLimitError(error)) {
                        return;
                    }

                    // Do not reveal whether the email address exists.
                    this.displayRecoveryInfo();
                });
        },

        handleRateLimitError(error: unknown): boolean {
            /* eslint-disable @typescript-eslint/no-unsafe-member-access */
            // @ts-expect-error
            let apiError = error?.response?.data?.errors as unknown;
            apiError = Array.isArray(apiError) ? apiError[0] : apiError;

            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            if (parseInt(apiError?.status, 10) !== 429) {
                return false;
            }

            // @ts-expect-error
            const seconds = Number(apiError?.meta?.parameters?.seconds) || 10;
            /* eslint-enable @typescript-eslint/no-unsafe-member-access */

            this.rateLimitMessage = this.$t('global.error-codes.FRAMEWORK__RATE_LIMIT_EXCEEDED', { seconds }, 0);
            this.$emit('is-not-loading');

            if (this.rateLimitTimeout) {
                clearTimeout(this.rateLimitTimeout);
            }

            this.rateLimitTimeout = setTimeout(() => {
                this.rateLimitMessage = '';
                this.rateLimitTimeout = null;
            }, seconds * 1000);

            return true;
        },

        displayRecoveryInfo() {
            void this.$router.push({
                name: 'sw.login.index.recoveryInfo',
                state: {
                    email: this.email,
                },
            });
        },
    },
});
