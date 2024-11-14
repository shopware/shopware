describe('system.store', () => {
    const store = Shopware.Store.get('system');

    beforeEach(() => {
        store.locales = [];
    });

    it('has initial state', () => {
        expect(store.locales).toEqual([]);
    });

    it('can register admin locale', () => {
        store.registerAdminLocale('en-GB');

        expect(store.locales).toStrictEqual(['en-GB']);
    });

    it('can register admin locale only once', () => {
        store.registerAdminLocale('en-GB');
        store.registerAdminLocale('en-GB');

        expect(store.locales).toStrictEqual(['en-GB']);
    });
});
