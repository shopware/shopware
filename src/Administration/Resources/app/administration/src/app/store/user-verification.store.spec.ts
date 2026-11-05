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
    });

    it('requests the modal', () => {
        store().request();

        expect(store().requested).toBe(true);
    });

    it('dismisses the request', () => {
        store().request();
        store().dismiss();

        expect(store().requested).toBe(false);
    });
});
