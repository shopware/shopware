/**
 * @package admin
 */

// eslint-disable-next-line filename-rules/match
import { createPinia, setActivePinia } from 'pinia';
import type { AdminMenuStore } from './admin-menu.store';
import type { AppModuleDefinition } from '../../core/service/api/app-modules.service';

describe('admin-menu.store', () => {
    let store: AdminMenuStore;
    const mockAppModuleDefinitions: AppModuleDefinition[] = [];

    beforeAll(() => {
        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
        Shopware.Service().register('menuService', () => ({
            getNavigationFromApps: jest.fn().mockReturnValue(mockAppModuleDefinitions),
        }));
    });

    beforeEach(() => {
        setActivePinia(createPinia());
        store = Shopware.Store.get('adminMenu');
    });

    it('has initial state', () => {
        expect(store.isExpanded).toBe(true);
        expect(store.expandedEntries).toStrictEqual([]);
        expect(store.adminModuleNavigation).toStrictEqual([]);
    });

    it('expands a menu entry with `expandMenuEntry`', () => {
        store.expandMenuEntry({ id: 'test' });
        expect(store.expandedEntries).toStrictEqual([{ id: 'test' }]);
    });

    it('collapses all menu entries with `clearExpandedMenuEntries`', () => {
        store.expandMenuEntry({ id: 'test' });
        expect(store.expandedEntries).toStrictEqual([{ id: 'test' }]);

        store.clearExpandedMenuEntries();
        expect(store.expandedEntries).toStrictEqual([]);
    });

    it('collapses a menu entry with `collapseMenuEntry`', () => {
        store.expandMenuEntry({ id: 'test1' });
        store.expandMenuEntry({ id: 'test2' });
        expect(store.expandedEntries).toContainEqual({ id: 'test1' });
        expect(store.expandedEntries).toContainEqual({ id: 'test2' });

        store.collapseMenuEntry({ id: 'test1' });
        expect(store.expandedEntries).not.toContainEqual({ id: 'test1' });
        expect(store.expandedEntries).toContainEqual({ id: 'test2' });

        store.collapseMenuEntry({ id: 'test2' });
        expect(store.expandedEntries).not.toContainEqual({ id: 'test1' });
        expect(store.expandedEntries).not.toContainEqual({ id: 'test2' });
    });

    it('collapses the sidebar with `collapseSidebar`', () => {
        expect(store.isExpanded).toBe(true);

        store.collapseSidebar();
        expect(store.isExpanded).toBe(false);
    });

    it('expands the sidebar with `expandSidebar`', () => {
        store.collapseSidebar();
        expect(store.isExpanded).toBe(false);

        store.expandSidebar();
        expect(store.isExpanded).toBe(true);
    });

    it('returns the app module navigation with `appModuleNavigation`', () => {
        expect(store.appModuleNavigation).toEqual(mockAppModuleDefinitions);
    });
});
