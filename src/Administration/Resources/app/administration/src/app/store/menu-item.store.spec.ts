/**
 * @sw-package framework
 */

import { createPinia, setActivePinia } from 'pinia';

describe('menuItem.store', () => {
    let store = Shopware.Store.get('menuItem');

    beforeEach(() => {
        setActivePinia(createPinia());
        store = Shopware.Store.get('menuItem');
    });

    it('has initial state', () => {
        expect(store.menuItems).toStrictEqual([]);
    });

    it('adds a menu item', () => {
        store.addMenuItem({
            label: 'test',
            parent: 'test',
            position: 0,
            moduleId: 'test',
        });

        expect(store.menuItems).toStrictEqual([
            {
                label: 'test',
                parent: 'test',
                position: 0,
                moduleId: 'test',
            },
        ]);
    });
});
