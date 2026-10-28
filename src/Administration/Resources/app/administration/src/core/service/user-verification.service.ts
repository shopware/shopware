/**
 * @sw-package framework
 */

import type { LoginConfig, LoginService } from './login.service';
import type SsoReauthService from './sso-reauth.service';
import UserVerificationError from './user-verification.error';

type ApiContext = typeof Shopware.Context.api;

const USER_VERIFIED_SCOPE = 'user-verified';

/**
 * Verifies the current user before a sensitive action, such as changing users, roles or access keys.
 *
 * `verify()` runs the flow that fits the session and resolves with an API context whose token the
 * action can use. A password session confirms its password in the verify user modal; an SSO session
 * signs in again at the identity provider in a separate window. Callers neither know nor care which.
 *
 * ```ts
 * this.userVerificationService.verify()
 *     .then((context) => this.repository.save(this.entity, context))
 *     .catch(() => {}); // cancelled, or the verification already told the user what went wrong
 * ```
 *
 * @public
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class UserVerificationService {
    private pending: { resolve: (context: ApiContext) => void; reject: (error: UserVerificationError) => void } | null =
        null;

    private running: Promise<ApiContext> | null = null;

    constructor(
        private readonly loginService: LoginService,
        private readonly ssoReauthService: SsoReauthService,
    ) {}

    /**
     * Resolves with the API context carrying the verified token. Rejects with a
     * {@link UserVerificationError}; `isCancelled` tells a user's cancel apart from a failed start.
     * A verification requested while one runs joins the running one.
     */
    verify(): Promise<ApiContext> {
        this.running ??= this.start().finally(() => {
            this.running = null;
        });

        return this.running;
    }

    /**
     * Whether the current access token already carries the `user-verified` scope.
     */
    isVerified(): boolean {
        const accessToken = this.loginService.getBearerAuthentication('access');

        return (
            typeof accessToken === 'string' && UserVerificationService.readScopes(accessToken).includes(USER_VERIFIED_SCOPE)
        );
    }

    /**
     * Whether the current session was issued by the identity provider: the login marked it as such,
     * or the shop offers no password login at all.
     */
    async isSsoSession(): Promise<boolean> {
        return this.isSsoSessionFor(await this.loadLoginConfig());
    }

    /**
     * Called by the verify user modal with the context the password confirmation produced.
     *
     * @private
     */
    complete(context: ApiContext): void {
        this.settle((pending) => pending.resolve(context));
    }

    /**
     * Called by the verify user modal when the user closes it.
     *
     * @private
     */
    cancel(): void {
        this.settle((pending) => pending.reject(UserVerificationError.cancelled()));
    }

    /**
     * Called by the verify user modal when the user picks the identity provider over the password.
     *
     * @private
     */
    async switchToSso(): Promise<void> {
        Shopware.Store.get('userVerification').dismiss();

        try {
            const context = await this.verifyWithSso();
            this.settle((pending) => pending.resolve(context));
        } catch (error) {
            this.settle((pending) => pending.reject(error as UserVerificationError));
        }
    }

    private async start(): Promise<ApiContext> {
        const loginConfig = await this.loadLoginConfig();

        if (this.isSsoSessionFor(loginConfig)) {
            return this.verifyWithSso();
        }

        return new Promise((resolve, reject) => {
            this.pending = { resolve, reject };
            Shopware.Store.get('userVerification').request(!!loginConfig.url);
        });
    }

    /**
     * Rejects with a {@link UserVerificationError} and tells the user about every failure but a cancel.
     */
    private async verifyWithSso(): Promise<ApiContext> {
        try {
            return await this.reauthenticateWithSso();
        } catch (error) {
            const verificationError =
                error instanceof UserVerificationError ? error : UserVerificationError.ssoNotConfigured();
            this.notifyFailure(verificationError);

            throw verificationError;
        }
    }

    private async reauthenticateWithSso(): Promise<ApiContext> {
        await this.ssoReauthService.reauthenticate();

        // the window stored the new session cookie; reading it also updates Shopware.Context.api.authToken
        if (!this.loginService.getBearerAuthentication()) {
            throw UserVerificationError.noSession();
        }

        sessionStorage.setItem('sw-sso-session', 'true');

        return { ...Shopware.Context.api };
    }

    private settle(outcome: (pending: NonNullable<typeof this.pending>) => void): void {
        const pending = this.pending;
        this.pending = null;

        Shopware.Store.get('userVerification').dismiss();

        if (pending) {
            outcome(pending);
        }
    }

    private isSsoSessionFor(loginConfig: LoginConfig): boolean {
        return sessionStorage.getItem('sw-sso-session') === 'true' || (!loginConfig.useDefault && !!loginConfig.url);
    }

    private async loadLoginConfig(): Promise<LoginConfig> {
        try {
            return await this.loginService.getLoginTemplateConfig();
        } catch {
            // without the SSO configuration the password login is the only one left
            return { useDefault: true, url: '' };
        }
    }

    private notifyFailure(error: UserVerificationError): void {
        if (error.isCancelled) {
            return;
        }

        const root = Shopware.Application.getApplicationRoot();

        void Shopware.Store.get('notification').createNotification({
            variant: 'error',
            title: root ? root.$t('global.default.error') : '',
            message: root ? root.$t(`global.sw-verify-user-modal.failure.${error.reason}`) : error.message,
        });
    }

    private static readScopes(accessToken: string): string[] {
        try {
            const payload = accessToken.split('.')[1] ?? '';
            const json = JSON.parse(window.atob(payload.replace(/-/g, '+').replace(/_/g, '/'))) as { scopes?: unknown };

            return Array.isArray(json.scopes)
                ? json.scopes.filter((scope): scope is string => typeof scope === 'string')
                : [];
        } catch {
            return [];
        }
    }
}
