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
 * `verify()` opens the verify user modal and resolves with an API context whose token the action can
 * use. A password session confirms its password there; an SSO session signs in again at the identity
 * provider in a separate window, opened by the modal's button. Callers neither know nor care which.
 *
 * ```ts
 * this.userVerificationService.verify()
 *     .then((context) => this.repository.save(this.entity, context))
 *     .catch(() => {}); // cancelled, or the verification already told the user what went wrong
 * ```
 *
 * @public
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- public API for extensions, see RELEASE_INFO
export default class UserVerificationService {
    private pending: { resolve: (context: ApiContext) => void; reject: (error: UserVerificationError) => void } | null =
        null;

    private running: Promise<ApiContext> | null = null;

    constructor(
        private readonly loginService: LoginService,
        private readonly ssoReauthService: SsoReauthService,
    ) {}

    /**
     * Resolves with the API context carrying the verified token. Rejects with a cancelled
     * {@link UserVerificationError} when the user closes the modal; a failed identity provider login
     * leaves the modal open for another try. A verification requested while one runs joins the running one.
     */
    verify(): Promise<ApiContext> {
        this.running ??= this.request().finally(() => {
            this.running = null;
        });

        return this.running;
    }

    /**
     * Whether the current access token already carries the `user-verified` scope.
     */
    isVerified(): boolean {
        const scopes = this.readAccessTokenPayload().scopes;

        return Array.isArray(scopes) && scopes.includes(USER_VERIFIED_SCOPE);
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
     * Called by the verify user modal before it renders: whether the session came from the identity
     * provider, and the identity provider's login URL, empty when the shop has none.
     *
     * @private
     */
    async getSsoOptions(): Promise<{ ssoSession: boolean; loginUrl: string }> {
        const loginConfig = await this.loadLoginConfig();

        // without a login URL the password is the only way left, whatever the session says
        return { ssoSession: !!loginConfig.url && this.isSsoSessionFor(loginConfig), loginUrl: loginConfig.url };
    }

    /**
     * Called by the verify user modal straight from its button, with the URL from {@link getSsoOptions}.
     * It opens the identity provider's window before the first await, inside the click's user activation.
     * Rejects with a {@link UserVerificationError} and tells the user about every failure but a cancel.
     *
     * @private
     */
    async verifyWithSso(loginUrl: string): Promise<ApiContext> {
        try {
            return await this.reauthenticateWithSso(loginUrl);
        } catch (error) {
            const verificationError =
                error instanceof UserVerificationError ? error : UserVerificationError.ssoNotConfigured();
            this.notifyFailure(verificationError);

            throw verificationError;
        }
    }

    private request(): Promise<ApiContext> {
        return new Promise((resolve, reject) => {
            this.pending = { resolve, reject };
            Shopware.Store.get('userVerification').request();
        });
    }

    private async reauthenticateWithSso(loginUrl: string): Promise<ApiContext> {
        const subject = this.readSubject();

        await this.ssoReauthService.reauthenticate(loginUrl);

        // the window stored the new session cookie; reading it also updates Shopware.Context.api.authToken
        if (!this.loginService.getBearerAuthentication()) {
            throw UserVerificationError.noSession();
        }

        // the identity provider lets the user sign in with another account, whose cookie now holds the session
        if (this.readSubject() !== subject) {
            this.loginService.logout();

            throw UserVerificationError.otherUser();
        }

        // only a fresh identity provider login gets the scope from the server; the popup's report alone proves nothing
        if (!this.isVerified()) {
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
            variant: 'critical',
            title: root ? root.$t('global.default.error') : '',
            message: root ? root.$t(`global.sw-verify-user-modal.failure.${error.reason}`) : error.message,
        });
    }

    private readSubject(): string | null {
        const subject = this.readAccessTokenPayload().sub;

        return typeof subject === 'string' ? subject : null;
    }

    private readAccessTokenPayload(): { scopes?: unknown; sub?: unknown } {
        const accessToken = this.loginService.getBearerAuthentication('access');

        if (typeof accessToken !== 'string') {
            return {};
        }

        try {
            const payload = accessToken.split('.')[1] ?? '';
            const json: unknown = JSON.parse(window.atob(payload.replace(/-/g, '+').replace(/_/g, '/')));

            return typeof json === 'object' && json !== null ? json : {};
        } catch {
            return {};
        }
    }
}
