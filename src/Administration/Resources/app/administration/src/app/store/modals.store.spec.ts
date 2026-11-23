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

    it('stores a custom z-index when provided', () => {
        store.openModal({
            locationId: 'test',
            title: 'Test Modal',
            closable: true,
            showHeader: true,
            showFooter: true,
            variant: 'default',
            zIndex: 2000,
            baseUrl: 'https://example.com',
        });

        expect(store.modals[0].zIndex).toBe(2000);
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

    describe('close results', () => {
        const openWithResult = (overrides = {}) => {
            const onResult = jest.fn();
            store.openModal({
                locationId: 'test',
                title: 'Test Modal',
                baseUrl: 'https://example.com/app',
                onResult,
                ...overrides,
            });

            return onResult;
        };

        it('reports a close by the extension with its data', () => {
            const onResult = openWithResult();

            store.closeModal('test', { saved: true });

            expect(onResult).toHaveBeenCalledWith({ reason: 'closed', data: { saved: true } });
        });

        it('reports a close by the extension without data', () => {
            const onResult = openWithResult();

            store.closeModal('test');

            expect(onResult).toHaveBeenCalledWith({ reason: 'closed' });
        });

        it('reports a close by the user as dismissed', () => {
            const onResult = openWithResult();

            store.dismissModal(store.modals[0]);

            expect(store.modals).toStrictEqual([]);
            expect(onResult).toHaveBeenCalledWith({ reason: 'dismissed' });
        });

        it('reports a close only once', () => {
            const onResult = openWithResult();

            store.closeModal('test');
            store.closeModal('test');

            expect(onResult).toHaveBeenCalledTimes(1);
        });

        it('replaces an open modal with the same locationId and dismisses the old one', () => {
            const first = openWithResult({ title: 'First' });
            const second = openWithResult({ title: 'Second' });

            expect(store.modals).toHaveLength(1);
            expect(store.modals[0].title).toBe('Second');
            expect(first).toHaveBeenCalledWith({ reason: 'dismissed' });
            expect(second).not.toHaveBeenCalled();
        });

        it('closes the modal when the opener cannot be reached', async () => {
            openWithResult({ onResult: jest.fn().mockRejectedValue(new Error('opener gone')) });

            store.closeModal('test');
            await flushPromises();

            expect(store.modals).toStrictEqual([]);
        });

        it('closes the modal when the opener callback throws', () => {
            openWithResult({
                onResult: () => {
                    throw new Error('opener gone');
                },
            });

            store.closeModal('test');

            expect(store.modals).toStrictEqual([]);
        });
    });

    describe('props', () => {
        it('returns the props to the extension that owns the modal', () => {
            store.openModal({ locationId: 'test', baseUrl: 'https://example.com/app', props: { tab: 'tone' } });

            expect(store.getProps('test', 'https://example.com')).toStrictEqual({ tab: 'tone' });
        });

        it('does not return the props to another origin', () => {
            store.openModal({ locationId: 'test', baseUrl: 'https://example.com/app', props: { tab: 'tone' } });

            expect(store.getProps('test', 'https://example.co')).toBeNull();
        });

        it('returns null for a modal without props', () => {
            store.openModal({ locationId: 'test', baseUrl: 'https://example.com/app' });

            expect(store.getProps('test', 'https://example.com')).toBeNull();
        });

        it('returns null for a location that is not open', () => {
            expect(store.getProps('test', 'https://example.com')).toBeNull();
        });
    });
});
