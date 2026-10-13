/**
 * @sw-package framework
 */

import type { LoginService } from './login.service';

const VERIFIED_AT_KEY = 'sw-sso-reauth-verified-at';
const RETURN_ROUTE_KEY = 'sw-sso-reauth-return-route';

/**
 * Re-authenticates an SSO session at the identity provider before a sensitive action.
 *
 * An SSO session has no Shopware password, so the verify user modal cannot ask for one. Instead the
 * user is sent through the identity provider login again with a forced prompt. Every completed SSO
 * login counts as a verification for {@link SsoReauthService.VERIFICATION_TTL} milliseconds, so the
 * first sensitive action right after signing in does not ask twice.
 *
 * The guarantee is client-side only: the backend does not require the `user-verified` scope from SSO
 * sessions, and the SSO grant cannot be issued that scope.
 *
 * @private
 */
export default class SsoReauthService {
    static readonly VERIFICATION_TTL = 15 * 60 * 1000;

    constructor(
        private readonly loginService: LoginService,
        private readonly navigate: (url: string) => void = (url) => {
            window.location.href = url;
        },
        private readonly now: () => number = () => Date.now(),
    ) {}

    isRecentlyVerified(): boolean {
        const verifiedAt = Number(sessionStorage.getItem(VERIFIED_AT_KEY));

        return verifiedAt > 0 && this.now() - verifiedAt < SsoReauthService.VERIFICATION_TTL;
    }

    /**
     * Remembers the route to return to and sends the browser to the identity provider with a forced login.
     * The page unloads, so the caller has nothing to do after this resolves.
     */
    async start(returnRoute: string): Promise<void> {
        const loginConfig = await this.loginService.getLoginTemplateConfig();

        if (loginConfig.useDefault || !loginConfig.url) {
            throw new Error('The SSO login is not configured');
        }

        const url = new URL(loginConfig.url);
        url.searchParams.set('usePromptLogin', '1');

        sessionStorage.setItem(RETURN_ROUTE_KEY, returnRoute);
        // the same markers the login page sets before forwarding to the identity provider
        sessionStorage.setItem('redirectFromLogin', 'true');
        sessionStorage.setItem('sw-sso-session', 'true');

        this.navigate(url.toString());
    }

    /**
     * Called once an SSO login completed. Starts the verification window and returns the route a
     * re-authentication was started from, or null when the login did not come from the modal.
     */
    complete(): string | null {
        sessionStorage.setItem(VERIFIED_AT_KEY, String(this.now()));

        const returnRoute = sessionStorage.getItem(RETURN_ROUTE_KEY);
        sessionStorage.removeItem(RETURN_ROUTE_KEY);

        return returnRoute;
    }
}
