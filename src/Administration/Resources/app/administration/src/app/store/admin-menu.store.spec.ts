/**
 * @sw-package framework
 */

import { createPinia, setActivePinia } from 'pinia';
import type { AdminMenuStore } from './admin-menu.store';
import type { AppModuleDefinition } from '../../core/service/api/app-modules.service';
import type { ModuleManifest } from '../../core/factory/module.factory';

type NavigationEntry = Exclude<ModuleManifest['navigation'], undefined>[number];

// Path-only entries are valid at runtime, but the Navigation type requires id, hence the cast
function pathOnlyEntry(path: string): NavigationEntry {
    return { path } as NavigationEntry;
}

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

    afterEach(() => {
        localStorage.removeItem('sw-admin-menu-sidebar-expanded');
        localStorage.removeItem('sw-admin-menu-expanded');
    });

    it('has initial state', () => {
        expect(store.isExpanded).toBe(true);
        expect(localStorage.getItem('sw-admin-menu-sidebar-expanded')).toBeNull();
        expect(store.expandedEntries).toStrictEqual([]);
        expect(store.adminModuleNavigation).toStrictEqual([]);
    });

    it('ignores the legacy sw-admin-menu-expanded value', () => {
        // Pre-rework versions auto-wrote 'false' on small viewports — not a user choice
        localStorage.setItem('sw-admin-menu-expanded', 'false');

        setActivePinia(createPinia());
        store = Shopware.Store.get('adminMenu');

        expect(store.isExpanded).toBe(true);
    });

    it('restores a persisted collapsed preference', () => {
        localStorage.setItem('sw-admin-menu-sidebar-expanded', 'false');

        setActivePinia(createPinia());
        store = Shopware.Store.get('adminMenu');

        expect(store.isExpanded).toBe(false);
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

    it('keys entries without an id by their path', () => {
        store.expandMenuEntry(pathOnlyEntry('sw.first.index'));
        store.expandMenuEntry(pathOnlyEntry('sw.second.index'));
        expect(store.expandedEntries).toHaveLength(2);

        store.collapseMenuEntry(pathOnlyEntry('sw.first.index'));

        expect(store.expandedEntries).toStrictEqual([{ path: 'sw.second.index' }]);
    });

    it('does not expand the same menu entry twice', () => {
        store.expandMenuEntry({ id: 'test' });
        store.expandMenuEntry({ id: 'test' });

        expect(store.expandedEntries).toStrictEqual([{ id: 'test' }]);
    });

    it('collapses the sidebar with `collapseSidebar`', () => {
        expect(store.isExpanded).toBe(true);
        expect(localStorage.getItem('sw-admin-menu-sidebar-expanded')).toBeNull();

        store.collapseSidebar();
        expect(store.isExpanded).toBe(false);
        expect(localStorage.getItem('sw-admin-menu-sidebar-expanded')).toBe('false');
    });

    it('expands the sidebar with `expandSidebar`', () => {
        store.collapseSidebar();
        expect(store.isExpanded).toBe(false);
        expect(localStorage.getItem('sw-admin-menu-sidebar-expanded')).toBe('false');

        store.expandSidebar();
        expect(store.isExpanded).toBe(true);
        expect(localStorage.getItem('sw-admin-menu-sidebar-expanded')).toBe('true');
    });

    it('returns the app module navigation with `appModuleNavigation`', () => {
        expect(store.appModuleNavigation).toEqual(mockAppModuleDefinitions);
    });
});
