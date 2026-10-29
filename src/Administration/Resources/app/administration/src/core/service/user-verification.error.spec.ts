/**
 * @sw-package framework
 */
import UserVerificationError from 'src/core/service/user-verification.error';
import type { UserVerificationFailure } from 'src/core/service/user-verification.error';

describe('src/core/service/user-verification.error.ts', () => {
    it.each<[UserVerificationFailure, UserVerificationError, boolean]>([
        ['cancelled', UserVerificationError.cancelled(), true],
        ['sso-not-configured', UserVerificationError.ssoNotConfigured(), false],
        ['sso-window-blocked', UserVerificationError.ssoWindowBlocked(), false],
        ['no-session', UserVerificationError.noSession(), false],
    ])('carries the reason "%s"', (reason, error, isCancelled) => {
        expect(error).toBeInstanceOf(Error);
        expect(error.name).toBe('UserVerificationError');
        expect(error.reason).toBe(reason);
        expect(error.message).toBe(`The user verification ended: ${reason}`);
        expect(error.isCancelled).toBe(isCancelled);
    });
});
