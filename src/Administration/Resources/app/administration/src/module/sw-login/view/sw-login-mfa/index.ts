/**
 * @sw-package framework
 */

import { isWebAuthnSupported } from 'src/core/helper/webauthn.helper';
import template from './sw-login-mfa.html.twig';
import './sw-login-mfa.scss';

const { Component, Mixin } = Shopware;

interface MfaData {
    code: string;
    useRecoveryCode: boolean;
    isLoading: boolean;
}

/**
 * Second-factor step of the admin login (feature flag ADMIN_AUTH). Rendered by `sw-login-login`
 * instead of the password form while a MFA challenge is pending. Deliberately not a route: a page
 * reload drops the in-memory pending token and falls back to the password step by design.
 *
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['loginService'],

    emits: [
        'mfa-success',
        'mfa-cancel',
        'is-loading',
        'is-not-loading',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        methods: {
            type: Array as PropType<string[]>,
            required: true,
        },
    },

    data(): MfaData {
        return {
            code: '',
            useRecoveryCode: false,
            isLoading: false,
        };
    },

    computed: {
        totpAvailable(): boolean {
            return this.methods.includes('totp');
        },

        recoveryAvailable(): boolean {
            return this.methods.includes('recovery_codes');
        },

        webauthnAvailable(): boolean {
            return this.methods.includes('webauthn') && isWebAuthnSupported();
        },

        showCodeField(): boolean {
            return this.useRecoveryCode ? this.recoveryAvailable : this.totpAvailable;
        },

        codeValid(): boolean {
            if (this.useRecoveryCode) {
                // Accept the code with or without the dash; the backend normalizes it.
                return this.code.replace(/[\s-]/g, '').length >= 6;
            }

            return /^\d{6}$/.test(this.code.trim());
        },
    },

    created(): void {
        // Without a TOTP enrollment the recovery-code input is the only code-based factor left.
        if (!this.totpAvailable && this.recoveryAvailable) {
            this.useRecoveryCode = true;
        }
    },

    methods: {
        toggleRecoveryCode() {
            this.useRecoveryCode = !this.useRecoveryCode;
            this.code = '';
        },

        verifyCode() {
            if (!this.codeValid || this.isLoading) {
                return Promise.resolve();
            }

            this.isLoading = true;
            this.$emit('is-loading');

            const method = this.useRecoveryCode ? 'recovery_codes' : 'totp';

            return this.loginService
                .verifySecondFactor(method, this.code.trim())
                .then(() => {
                    this.code = '';
                    this.$emit('mfa-success');
                })
                .catch(() => {
                    this.code = '';
                    this.createNotificationError({
                        message: this.$t('sw-login.mfa.invalidCode'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                    this.$emit('is-not-loading');
                });
        },

        verifyWithPasskey() {
            if (this.isLoading) {
                return Promise.resolve();
            }

            this.isLoading = true;
            this.$emit('is-loading');

            return this.loginService
                .verifySecondFactorWebauthn()
                .then(() => {
                    this.code = '';
                    this.$emit('mfa-success');
                })
                .catch((error: unknown) => {
                    // Ceremony / client-side errors carry a user-facing message (e.g. "cancelled");
                    // server-side verification failures get the generic snippet.
                    const hasResponse = !!(error as { response?: unknown })?.response;
                    const message = !hasResponse && error instanceof Error && error.message ? error.message : null;

                    this.createNotificationError({
                        message: message ?? this.$t('sw-login.mfa.invalidCode'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                    this.$emit('is-not-loading');
                });
        },

        cancel() {
            this.code = '';
            this.useRecoveryCode = false;
            this.$emit('mfa-cancel');
        },
    },
});
