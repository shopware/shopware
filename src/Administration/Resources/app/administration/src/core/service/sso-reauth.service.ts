/**
 * @sw-package framework
 */

import type { LoginService } from './login.service';

const RETURN_ROUTE_KEY = 'sw-sso-reauth-return-route';
const USER_VERIFIED_SCOPE = 'user-verified';

/**
 * Re-authenticates an SSO session at the identity provider before a sensitive action.
 *
 * An SSO session has no Shopware password, so the verify user modal cannot ask for one. Instead the
 * user is sent through the identity provider login again with a forced prompt. The backend puts the
 * `user-verified` scope on the access token when the identity provider reports a fresh login, so the
 * token itself says whether the session counts as verified.
 *
 * @private
 */
export default class SsoReauthService {
    constructor(
        private readonly loginService: LoginService,
        private readonly navigate: (url: string) => void = (url) => {
            window.location.href = url;
        },
    ) {}

    /**
     * Whether the current access token carries the `user-verified` scope.
     */
    isVerified(): boolean {
        const accessToken = this.loginService.getBearerAuthentication('access');

        return typeof accessToken === 'string' && SsoReauthService.readScopes(accessToken).includes(USER_VERIFIED_SCOPE);
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
     * Called once an SSO login completed. Returns the route a re-authentication was started from,
     * or null when the login did not come from the modal.
     */
    complete(): string | null {
        const returnRoute = sessionStorage.getItem(RETURN_ROUTE_KEY);
        sessionStorage.removeItem(RETURN_ROUTE_KEY);

        return returnRoute;
    }

    /**
     * The access token is a JWT whose payload lists the granted scope identifiers; a token that cannot be
     * read yields no scopes, so the session is treated as unverified.
     */
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
