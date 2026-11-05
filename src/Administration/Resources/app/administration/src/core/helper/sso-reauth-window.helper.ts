/**
 * @sw-package framework
 */

/**
 * The window name the user verification opens the identity provider in.
 *
 * @private
 */
export const SSO_REAUTH_WINDOW_NAME = 'sw-sso-reauth';

/**
 * The message the re-authentication window posts to its opener once the identity provider sent it back.
 *
 * @private
 */
export const SSO_REAUTH_COMPLETE_MESSAGE = 'sw-sso-reauth-complete';

/**
 * Ends the SSO re-authentication window before the administration boots in it.
 *
 * The identity provider sends the window back to the administration with the new session cookie set.
 * The opener owns that session, so this window only reports back and closes. It stays open and boots
 * when the SSO login ended on the error page, which the user has to read.
 *
 * @private
 *
 * @returns whether the window was handled; the caller stops booting in that case
 */
export function completeSsoReauthWindow(win: Window = window): boolean {
    if (win.name !== SSO_REAUTH_WINDOW_NAME || !win.opener) {
        return false;
    }

    if (win.location.hash.includes('/sso/error')) {
        return false;
    }

    (win.opener as Window).postMessage(SSO_REAUTH_COMPLETE_MESSAGE, win.location.origin);
    win.close();

    return true;
}
