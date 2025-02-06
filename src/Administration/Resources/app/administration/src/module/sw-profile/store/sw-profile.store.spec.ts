describe('sw-profile.store', () => {
    it('has initial state', () => {
        const store = Shopware.Store.get('swProfile');
        expect(store.searchPreferences).toStrictEqual([]);
        expect(store.userSearchPreferences).toBeNull();
    });
});
