/**
 * @sw-package framework
 */
import initializeUserVerification from 'src/app/init/user-verification.init';

describe('src/app/init/user-verification.init.ts', () => {
    it('registers the user verification store', () => {
        initializeUserVerification();

        const store = Shopware.Store.get('userVerification');

        expect(store.requested).toBe(false);
        expect(store.ssoAvailable).toBe(false);
    });
});
