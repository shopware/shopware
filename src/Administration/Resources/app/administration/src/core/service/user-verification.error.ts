/**
 * @sw-package framework
 */

/**
 * Why a user verification ended without a verified token.
 *
 * @public
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- public API for extensions, see RELEASE_INFO
export type UserVerificationFailure = 'cancelled' | 'sso-not-configured' | 'sso-window-blocked' | 'no-session';

/**
 * Rejection of {@link UserVerificationService.verify}. `cancelled` is the user's choice and needs no
 * message; the other reasons are shown to the user by the verification itself.
 *
 * @public
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations -- public API for extensions, see RELEASE_INFO
export default class UserVerificationError extends Error {
    constructor(readonly reason: UserVerificationFailure) {
        super(`The user verification ended: ${reason}`);
        this.name = 'UserVerificationError';
    }

    static cancelled(): UserVerificationError {
        return new UserVerificationError('cancelled');
    }

    static ssoNotConfigured(): UserVerificationError {
        return new UserVerificationError('sso-not-configured');
    }

    static ssoWindowBlocked(): UserVerificationError {
        return new UserVerificationError('sso-window-blocked');
    }

    static noSession(): UserVerificationError {
        return new UserVerificationError('no-session');
    }

    get isCancelled(): boolean {
        return this.reason === 'cancelled';
    }
}
