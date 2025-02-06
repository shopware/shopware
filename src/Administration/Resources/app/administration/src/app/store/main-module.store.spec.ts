describe('main-module.store', () => {
    const store = Shopware.Store.get('extensionMainModules');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.mainModules).toStrictEqual([]);
    });

    it('can add a main module', () => {
        const mainModule = {
            extensionName: 'demo-extension',
            moduleId: 'demo-module',
        };

        store.addMainModule(mainModule);
        expect(store.mainModules).toStrictEqual([mainModule]);
    });
});
