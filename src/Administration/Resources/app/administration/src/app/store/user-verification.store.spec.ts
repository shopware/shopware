/**
 * @sw-package framework
 */
import './user-verification.store';

describe('src/app/store/user-verification.store.ts', () => {
    const store = () => Shopware.Store.get('userVerification');

    beforeEach(() => {
        store().dismiss();
    });

    it('requests nothing initially', () => {
        expect(store().requested).toBe(false);
        expect(store().ssoAvailable).toBe(false);
    });

    it('requests the modal with the identity provider offered', () => {
        store().request(true);

        expect(store().requested).toBe(true);
        expect(store().ssoAvailable).toBe(true);
    });

    it('dismisses the request', () => {
        store().request(true);
        store().dismiss();

        expect(store().requested).toBe(false);
        expect(store().ssoAvailable).toBe(false);
    });
});
