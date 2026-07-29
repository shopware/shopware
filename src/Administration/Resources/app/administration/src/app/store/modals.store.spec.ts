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
                textContent: undefined,
                buttons: [],
                baseUrl: 'https://example.com',
            },
        ]);
    });

    it('opens a modal without a locationID but with textContent', () => {
        store.openModal({
            title: 'Test Modal',
            closable: true,
            showHeader: true,
            showFooter: true,
            variant: 'default',
            textContent: 'Test content',
            baseUrl: 'https://example.com',
        });

        expect(store.modals).toStrictEqual([
            {
                locationId: undefined,
                title: 'Test Modal',
                closable: true,
                showHeader: true,
                showFooter: true,
                variant: 'default',
                textContent: 'Test content',
                buttons: [],
                baseUrl: 'https://example.com',
            },
        ]);
    });

    it('closes a modal with locationId', () => {
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

    it('closes a modal without locationId', () => {
        store.openModal({
            title: 'Test Modal',
            closable: true,
            showHeader: true,
            showFooter: true,
            variant: 'default',
            textContent: 'Test content',
            baseUrl: 'https://example.com',
        });

        store.openModal({
            title: 'Test Modal 2',
            closable: true,
            showHeader: true,
            showFooter: true,
            variant: 'default',
            textContent: 'Test content 2',
            baseUrl: 'https://example.com',
        });

        store.openModal({
            locationId: 'test',
            title: 'Test Modal',
            closable: true,
            showHeader: true,
            showFooter: true,
            variant: 'default',
            baseUrl: 'https://example.com',
        });

        store.closeLastModalWithoutLocationId();

        expect(store.modals).toStrictEqual([
            {
                locationId: undefined,
                title: 'Test Modal',
                closable: true,
                showHeader: true,
                showFooter: true,
                variant: 'default',
                textContent: 'Test content',
                buttons: [],
                baseUrl: 'https://example.com',
            },
            {
                locationId: 'test',
                title: 'Test Modal',
                closable: true,
                showHeader: true,
                showFooter: true,
                variant: 'default',
                textContent: undefined,
                buttons: [],
                baseUrl: 'https://example.com',
            },
        ]);
    });
});
