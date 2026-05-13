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

    emits: ['is-loading'],

    inject: [
        'validationApiService',
    ],

    data() {
        return {
            email: '',
            isEmailValid: false,
        };
    },

    mounted() {
        this.mountedComponent();
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
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-member-access
                    this.displayRecoveryInfo(error.response.data);
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
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-member-access
                    this.displayRecoveryInfo(error.response.data);
                });
        },

        displayRecoveryInfo(data = null) {
            let seconds = 0;

            if (data !== null) {
                // @ts-expect-error
                let error = data?.errors as unknown;

                error = Array.isArray(error) ? error[0] : error;

                // @ts-expect-error
                // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
                if (parseInt(error?.status, 10) === 429) {
                    // @ts-expect-error
                    // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-member-access
                    seconds = error?.meta?.parameters?.seconds;
                }
            }

            void this.$router.push({
                name: 'sw.login.index.recoveryInfo',
                params: {
                    waitTime: seconds,
                },
            });
        },
    },
});
