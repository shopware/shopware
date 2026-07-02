/**
 * @sw-package framework
 */

import type { MfaMethod, TotpRegistrationOptions } from 'src/core/service/api/admin-auth.service';
import type { PublicKeyCredentialCreationOptionsJson } from 'src/core/helper/webauthn.helper';
import { createCredential, isWebAuthnSupported } from 'src/core/helper/webauthn.helper';
import template from './sw-profile-index-security.html.twig';
import { toSvgDataUri } from './qrcode';
import './sw-profile-index-security.scss';

const { Component, Mixin } = Shopware;

type PendingAction = 'enroll' | 'confirm' | 'passkey' | 'recovery' | 'delete' | null;

interface SecurityData {
    isLoading: boolean;
    methods: MfaMethod[];
    verifiedToken: string | null;
    showVerifyModal: boolean;
    pendingAction: PendingAction;
    showEnroll: boolean;
    enrollLabel: string;
    enrollment: TotpRegistrationOptions | null;
    confirmCode: string;
    isConfirming: boolean;
    isEnrollingPasskey: boolean;
    isGeneratingRecovery: boolean;
    recoveryCodes: string[] | null;
    recoverySaved: boolean;
    deleteCandidate: MfaMethod | null;
    pendingDeleteId: string | null;
}

/**
 * "Security" tab of the user profile (feature flag ADMIN_AUTH): manage the current user's own
 * second factors — TOTP authenticator apps, passkeys and recovery codes.
 *
 * All enrollment mutations hit the `_action/admin-auth/mfa/...` self-service routes, which require
 * an admin token carrying the `user-verified` OAuth scope. That token is obtained through the core
 * `sw-verify-user-modal` (a fresh password re-verification via `loginService.verifyUserToken`) and
 * captured from the modal's `verified` event, because the global bearer may be refreshed back to a
 * non-verified token by the app's refresh loop. The read-only method listing works without
 * re-verification.
 *
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['adminAuthService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data(): SecurityData {
        return {
            isLoading: false,
            methods: [],
            verifiedToken: null,
            showVerifyModal: false,
            pendingAction: null,
            showEnroll: false,
            enrollLabel: '',
            enrollment: null,
            confirmCode: '',
            isConfirming: false,
            isEnrollingPasskey: false,
            isGeneratingRecovery: false,
            recoveryCodes: null,
            recoverySaved: false,
            deleteCandidate: null,
            pendingDeleteId: null,
        };
    },

    computed: {
        confirmCodeValid(): boolean {
            return /^\d{6}$/.test(this.confirmCode.trim());
        },

        passkeySupported(): boolean {
            return isWebAuthnSupported();
        },

        recoveryMethod(): MfaMethod | null {
            return this.methods.find((method) => method.type === 'recovery_codes') ?? null;
        },

        qrCodeDataUri(): string {
            if (!this.enrollment?.otpauthUri) {
                return '';
            }

            try {
                return toSvgDataUri(this.enrollment.otpauthUri);
            } catch {
                // QR rendering must never break the page; the secret + URI text is shown as fallback.
                return '';
            }
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            void this.loadMethods();
        },

        /**
         * Explicit Authorization header with the captured user-verified token for the
         * enrollment-mutating requests.
         */
        verifiedHeaders(): Record<string, string> {
            if (!this.verifiedToken) {
                return {};
            }

            return { Authorization: `Bearer ${this.verifiedToken}` };
        },

        /**
         * Runs the action directly when the user already re-verified in this session, otherwise
         * remembers it and opens the password confirmation modal first.
         */
        runVerified(action: Exclude<PendingAction, null>) {
            if (this.verifiedToken) {
                void this.executeAction(action);

                return;
            }

            this.pendingAction = action;
            this.showVerifyModal = true;
        },

        onVerified(context: { authToken?: { access?: string } }) {
            this.showVerifyModal = false;
            this.verifiedToken = context?.authToken?.access ?? null;

            const action = this.pendingAction;
            this.pendingAction = null;

            if (action) {
                void this.executeAction(action);
            }
        },

        onVerifyClose() {
            this.showVerifyModal = false;

            if (!this.verifiedToken) {
                this.pendingAction = null;
                this.pendingDeleteId = null;
            }
        },

        async executeAction(action: Exclude<PendingAction, null>) {
            if (action === 'enroll') {
                await this.doEnroll();
            } else if (action === 'confirm') {
                await this.doConfirm();
            } else if (action === 'passkey') {
                await this.doEnrollPasskey();
            } else if (action === 'recovery') {
                await this.doGenerateRecovery();
            } else if (action === 'delete') {
                const id = this.pendingDeleteId;
                this.pendingDeleteId = null;
                await this.doDelete(id);
            }
        },

        async loadMethods() {
            this.isLoading = true;

            try {
                this.methods = await this.adminAuthService.getMfaMethods();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-profile.tabSecurity.genericError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        onStartEnroll() {
            this.showEnroll = true;
            this.enrollment = null;
            this.confirmCode = '';

            if (!this.enrollLabel) {
                this.enrollLabel = this.$t('sw-profile.tabSecurity.defaultLabel');
            }
        },

        onCancelEnroll() {
            this.showEnroll = false;
            this.enrollment = null;
            this.enrollLabel = '';
            this.confirmCode = '';
        },

        onSubmitEnroll() {
            this.runVerified('enroll');
        },

        async doEnroll() {
            this.isLoading = true;

            try {
                this.enrollment = await this.adminAuthService.totpRegisterOptions(this.enrollLabel, this.verifiedHeaders());
                this.confirmCode = '';
            } catch (error) {
                this.handleApiError(error);
            } finally {
                this.isLoading = false;
            }
        },

        onSubmitConfirm() {
            if (!this.confirmCodeValid) {
                return;
            }

            this.runVerified('confirm');
        },

        async doConfirm() {
            if (!this.enrollment?.id) {
                return;
            }

            this.isConfirming = true;

            try {
                await this.adminAuthService.totpRegisterVerify(
                    this.enrollment.id,
                    this.confirmCode.trim(),
                    this.verifiedHeaders(),
                );

                this.createNotificationSuccess({
                    message: this.$t('sw-profile.tabSecurity.confirmSuccess'),
                });

                this.onCancelEnroll();
                await this.loadMethods();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-profile.tabSecurity.confirmError'),
                });
            } finally {
                this.isConfirming = false;
            }
        },

        onAddPasskey() {
            if (!this.passkeySupported) {
                return;
            }

            this.runVerified('passkey');
        },

        async doEnrollPasskey() {
            if (this.isEnrollingPasskey) {
                return;
            }

            this.isEnrollingPasskey = true;
            this.isLoading = true;

            try {
                const registration = await this.adminAuthService.webauthnRegisterOptions(this.verifiedHeaders());
                const credential = await createCredential(
                    registration.options as unknown as PublicKeyCredentialCreationOptionsJson,
                );

                await this.adminAuthService.webauthnRegisterVerify(
                    {
                        credential: JSON.stringify(credential),
                        challengeToken: registration.challengeToken,
                        label: this.$t('sw-profile.tabSecurity.passkeyDefaultLabel'),
                    },
                    this.verifiedHeaders(),
                );

                this.createNotificationSuccess({
                    message: this.$t('sw-profile.tabSecurity.passkeySuccess'),
                });

                await this.loadMethods();
            } catch (error) {
                // A friendly ceremony/cancel error carries a plain message; API errors get handled.
                if (this.isApiError(error)) {
                    this.handleApiError(error);
                } else {
                    this.createNotificationError({
                        message: (error as Error)?.message || this.$t('sw-profile.tabSecurity.passkeyError'),
                    });
                }
            } finally {
                this.isEnrollingPasskey = false;
                this.isLoading = false;
            }
        },

        onGenerateRecovery() {
            if (this.isGeneratingRecovery) {
                return;
            }

            this.runVerified('recovery');
        },

        async doGenerateRecovery() {
            if (this.isGeneratingRecovery) {
                return;
            }

            this.isGeneratingRecovery = true;

            try {
                this.recoveryCodes = await this.adminAuthService.generateRecoveryCodes(this.verifiedHeaders());
                this.recoverySaved = false;
            } catch (error) {
                this.handleApiError(error);
            } finally {
                this.isGeneratingRecovery = false;
            }
        },

        recoveryCodesAsText(): string {
            return (this.recoveryCodes ?? []).join('\n');
        },

        async onCopyRecoveryCodes() {
            try {
                await navigator.clipboard.writeText(this.recoveryCodesAsText());

                this.createNotificationSuccess({
                    message: this.$t('sw-profile.tabSecurity.recoveryCopySuccess'),
                });
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-profile.tabSecurity.recoveryCopyError'),
                });
            }
        },

        async onConfirmRecoverySaved() {
            this.recoveryCodes = null;
            this.recoverySaved = false;

            await this.loadMethods();
        },

        onDelete(method: MfaMethod) {
            this.deleteCandidate = method;
        },

        onCloseDeleteModal() {
            this.deleteCandidate = null;
        },

        onConfirmDelete() {
            if (!this.deleteCandidate) {
                return;
            }

            this.pendingDeleteId = this.deleteCandidate.id;
            this.deleteCandidate = null;

            this.runVerified('delete');
        },

        async doDelete(id: string | null) {
            if (!id) {
                return;
            }

            this.isLoading = true;

            try {
                await this.adminAuthService.deleteMfaMethod(id, this.verifiedHeaders());

                this.createNotificationSuccess({
                    message: this.$t('sw-profile.tabSecurity.deleteSuccess'),
                });

                await this.loadMethods();
            } catch (error) {
                this.handleApiError(error);
            } finally {
                this.isLoading = false;
            }
        },

        isApiError(error: unknown): boolean {
            return typeof error === 'object' && error !== null && 'response' in error;
        },

        handleApiError(error: unknown) {
            // A 401/403 most likely means the verified token expired - ask to verify again.
            const status = (error as { response?: { status?: number } })?.response?.status;

            if (status === 401 || status === 403) {
                this.verifiedToken = null;
                this.createNotificationError({
                    message: this.$t('sw-profile.tabSecurity.reverifyError'),
                });

                return;
            }

            this.createNotificationError({
                message: this.$t('sw-profile.tabSecurity.genericError'),
            });
        },

        typeLabelFor(type: string): string {
            const key = `sw-profile.tabSecurity.types.${type}`;
            const label = this.$t(key);

            // `$t` returns the key itself when there is no translation; fall back to the raw type.
            return label === key ? type : label;
        },

        formatDate(value: string | null): string {
            if (!value) {
                return '-';
            }

            try {
                return Shopware.Utils.format.date(value);
            } catch {
                return value;
            }
        },
    },
});
