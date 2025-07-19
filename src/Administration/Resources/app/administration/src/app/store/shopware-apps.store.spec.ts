/**
 * @sw-package framework
 */
describe('shopware-apps.store', () => {
    const store = Shopware.Store.get('shopwareApps');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.apps).toStrictEqual([]);
        expect(store.selectedIds).toStrictEqual([]);
    });
});
