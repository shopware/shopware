/**
 * @sw-package framework
 */

import type { LoginService } from './login.service';
import UserVerificationError from './user-verification.error';
import { SSO_REAUTH_COMPLETE_MESSAGE, SSO_REAUTH_WINDOW_NAME } from '../helper/sso-reauth-window.helper';

type OpenWindow = (url: string, name: string, features: string) => Window | null;
type WindowEvents = Pick<Window, 'addEventListener' | 'removeEventListener'>;

/**
 * Re-authenticates an SSO session at the identity provider in a separate window.
 *
 * An SSO session has no Shopware password to verify. The identity provider login runs again with a
 * forced prompt in a popup, which ends on the administration URL with the new session cookie set and
 * reports back to this window, see {@link completeSsoReauthWindow}. The page the user works on stays
 * as it is.
 *
 * @private
 */
export default class SsoReauthService {
    /**
     * Grace period after the window closed, so a completion message posted right before the close wins.
     */
    static readonly CLOSE_GRACE_PERIOD = 250;

    constructor(
        private readonly loginService: LoginService,
        private readonly openWindow: OpenWindow = (url, name, features) => window.open(url, name, features),
        private readonly windowEvents: WindowEvents = window,
        private readonly origin: string = window.location.origin,
        private readonly pollInterval = 500,
    ) {}

    /**
     * Resolves once the identity provider sent the window back with a new session. Rejects when the
     * SSO login is not configured, the window was blocked, or the user closed it.
     */
    async reauthenticate(): Promise<void> {
        const loginConfig = await this.loginService.getLoginTemplateConfig();

        if (!loginConfig.url) {
            throw UserVerificationError.ssoNotConfigured();
        }

        const url = new URL(loginConfig.url);
        url.searchParams.set('usePromptLogin', '1');

        const popup = this.openWindow(url.toString(), SSO_REAUTH_WINDOW_NAME, 'popup,width=600,height=720');

        if (!popup) {
            throw UserVerificationError.ssoWindowBlocked();
        }

        return this.waitForCompletion(popup);
    }

    private waitForCompletion(popup: Window): Promise<void> {
        return new Promise((resolve, reject) => {
            let settled = false;
            const cleanups: Array<() => void> = [];

            const settle = (outcome: () => void) => {
                if (settled) {
                    return;
                }

                settled = true;
                cleanups.forEach((cleanup) => cleanup());
                outcome();
            };

            const onMessage = (event: Event) => {
                const { origin, data } = event as MessageEvent<unknown>;

                if (origin !== this.origin || data !== SSO_REAUTH_COMPLETE_MESSAGE) {
                    return;
                }

                settle(resolve);
            };

            const closePoll = setInterval(() => {
                if (!popup.closed) {
                    return;
                }

                clearInterval(closePoll);
                setTimeout(
                    () => settle(() => reject(UserVerificationError.cancelled())),
                    SsoReauthService.CLOSE_GRACE_PERIOD,
                );
            }, this.pollInterval);

            cleanups.push(
                () => clearInterval(closePoll),
                () => this.windowEvents.removeEventListener('message', onMessage),
            );
            this.windowEvents.addEventListener('message', onMessage);
        });
    }
}
