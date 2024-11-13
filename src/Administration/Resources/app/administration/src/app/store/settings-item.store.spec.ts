describe('settings-item.store', () => {
    const store = Shopware.Store.get('settingsItems');

    beforeEach(() => {
        store.$reset();
    });

    it('has initial state', () => {
        expect(store.settingsGroups).toStrictEqual({
            shop: [],
            system: [],
            plugins: [],
        });
    });

    it('should add item', () => {
        Shopware.Store.get('settingsItems').addItem({
            group: 'shop',
            name: 'item1',
            icon: 'icon',
            label: 'Item Example',
            to: {
                name: 'sw.settings.index.shop',
            },
        });

        expect(store.settingsGroups.shop).toStrictEqual([
            {
                group: 'shop',
                name: 'item1',
                icon: 'icon',
                label: 'Item Example',
                to: {
                    name: 'sw.settings.index.shop',
                },
            },
        ]);

        Shopware.Store.get('settingsItems').addItem({
            group: 'system',
            name: 'item2',
            icon: 'icon',
            label: 'Item Example',
            to: {
                name: 'sw.settings.index.shop',
            },
        });

        expect(store.settingsGroups.system).toStrictEqual([
            {
                group: 'system',
                name: 'item2',
                icon: 'icon',
                label: 'Item Example',
                to: {
                    name: 'sw.settings.index.shop',
                },
            },
        ]);

        Shopware.Store.get('settingsItems').addItem({
            group: 'plugins',
            name: 'item3',
            icon: 'icon',
            label: 'Item Example',
            to: {
                name: 'sw.settings.index.shop',
            },
        });

        expect(store.settingsGroups.plugins).toStrictEqual([
            {
                group: 'plugins',
                name: 'item3',
                icon: 'icon',
                label: 'Item Example',
                to: {
                    name: 'sw.settings.index.shop',
                },
            },
        ]);
    });
});
