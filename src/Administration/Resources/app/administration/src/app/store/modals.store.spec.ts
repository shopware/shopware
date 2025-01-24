/**
 * @sw-package framework
 */

import { createPinia, setActivePinia } from 'pinia';

describe('modals.store', () => {
    let store = Shopware.Store.get('modals');

    beforeEach(() => {
        setActivePinia(createPinia());
        store = Shopware.Store.get('modals');
    });

    it('has initial state', () => {
        expect(store.modals).toStrictEqual([]);
    });

    it('opens a modal', () => {
        store.openModal({
            locationId: 'test',
            title: 'Test Modal',
            closable: true,
            showHeader: true,
            showFooter: true,
            variant: 'default',
            baseUrl: 'https://example.com',
        });

        expect(store.modals).toStrictEqual([
            {
                locationId: 'test',
                title: 'Test Modal',
                closable: true,
                showHeader: true,
                showFooter: true,
                variant: 'default',
                buttons: [],
                baseUrl: 'https://example.com',
            },
        ]);
    });

    it('closes a modal', () => {
        store.openModal({
            locationId: 'test',
            title: 'Test Modal',
            closable: true,
            showHeader: true,
            showFooter: true,
            variant: 'default',
            baseUrl: 'https://example.com',
        });

        store.closeModal('test');

        expect(store.modals).toStrictEqual([]);
    });
});
