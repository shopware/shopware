/**
 * @sw-package framework
 */

import type { SidebarStore } from './sidebar.store';

describe('src/app/store/sidebar.store.ts', () => {
    let store: SidebarStore;

    function addSidebar(locationId: string) {
        store.addSidebar({
            locationId,
            title: `${locationId} title`,
            icon: 'regular-star',
            resizable: true,
            baseUrl: '',
            active: false,
        });
    }

    beforeEach(() => {
        store = Shopware.Store.get('sidebar');
        store.sidebars = [];
        store.closingSidebar = null;
        store.switchedWhileOpen = false;

        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it('deactivates the sidebar once the close animation finished', () => {
        addSidebar('a');
        store.setActiveSidebar('a');

        store.requestCloseSidebar('a');

        expect(store.closingSidebar).toBe('a');
        expect(store.sidebars[0].active).toBe(true);

        jest.advanceTimersByTime(400);

        expect(store.closingSidebar).toBeNull();
        expect(store.sidebars[0].active).toBe(false);
    });

    it('deactivates the sidebar immediately when the user prefers reduced motion', () => {
        // jsdom has no matchMedia implementation
        const originalMatchMedia = window.matchMedia;
        Object.defineProperty(window, 'matchMedia', {
            value: jest.fn().mockReturnValue({ matches: true } as MediaQueryList),
            configurable: true,
            writable: true,
        });

        try {
            addSidebar('a');
            store.setActiveSidebar('a');

            store.requestCloseSidebar('a');

            expect(store.closingSidebar).toBeNull();
            expect(store.sidebars[0].active).toBe(false);
        } finally {
            Object.defineProperty(window, 'matchMedia', {
                value: originalMatchMedia,
                configurable: true,
                writable: true,
            });
        }
    });

    it('ignores a repeated close request for the same sidebar', () => {
        addSidebar('a');
        store.setActiveSidebar('a');

        store.requestCloseSidebar('a');
        jest.advanceTimersByTime(200);
        store.requestCloseSidebar('a');
        jest.advanceTimersByTime(200);

        // The second request must not restart the animation window.
        expect(store.sidebars[0].active).toBe(false);
        expect(store.closingSidebar).toBeNull();
    });

    it('keeps closing the active sidebar when an inactive sidebar requests to close', () => {
        addSidebar('a');
        addSidebar('b');
        store.setActiveSidebar('a');

        store.requestCloseSidebar('a');
        store.requestCloseSidebar('b');
        jest.advanceTimersByTime(400);

        expect(store.sidebars[0].active).toBe(false);
        expect(store.closingSidebar).toBeNull();
    });

    it('keeps the sidebar open when it is reactivated during the close animation', () => {
        addSidebar('a');
        store.setActiveSidebar('a');

        store.requestCloseSidebar('a');
        store.setActiveSidebar('a');

        jest.advanceTimersByTime(400);

        expect(store.sidebars[0].active).toBe(true);
        expect(store.closingSidebar).toBeNull();
    });

    it('opens another sidebar during the close animation without replaying the open animation state', () => {
        addSidebar('a');
        addSidebar('b');
        store.setActiveSidebar('a');

        store.requestCloseSidebar('a');
        store.setActiveSidebar('b');

        // Mid-close activation is not a switch of an open panel.
        expect(store.switchedWhileOpen).toBe(false);
        expect(store.closingSidebar).toBeNull();

        jest.advanceTimersByTime(400);

        expect(store.getActiveSidebar?.locationId).toBe('b');
    });

    it('marks a switch when another sidebar is activated while one is open', () => {
        addSidebar('a');
        addSidebar('b');
        store.setActiveSidebar('a');

        store.setActiveSidebar('b');

        expect(store.switchedWhileOpen).toBe(true);
        expect(store.getActiveSidebar?.locationId).toBe('b');
        expect(store.sidebars.filter((sidebar) => sidebar.active)).toHaveLength(1);
    });

    it('does nothing for an unknown location id', () => {
        addSidebar('a');
        store.setActiveSidebar('a');

        store.setActiveSidebar('unknown');
        store.requestCloseSidebar('unknown');
        jest.advanceTimersByTime(400);

        expect(store.getActiveSidebar?.locationId).toBe('a');
    });

    it('clears a pending close when the sidebar is closed directly', () => {
        addSidebar('a');
        store.setActiveSidebar('a');

        store.requestCloseSidebar('a');
        store.closeSidebar('a');

        expect(store.sidebars[0].active).toBe(false);
        expect(store.closingSidebar).toBeNull();
    });

    it('clears a pending close when the sidebar is removed', () => {
        addSidebar('a');
        store.setActiveSidebar('a');

        store.requestCloseSidebar('a');
        store.removeSidebar('a');

        expect(store.sidebars).toHaveLength(0);
        expect(store.closingSidebar).toBeNull();
    });

    it('toggles the active sidebar closed and an inactive one open', () => {
        addSidebar('a');

        store.toggleSidebar('a');
        expect(store.getActiveSidebar?.locationId).toBe('a');

        store.toggleSidebar('a');
        expect(store.closingSidebar).toBe('a');

        // Toggling again while mid-close reopens instead of closing twice.
        store.toggleSidebar('a');
        expect(store.closingSidebar).toBeNull();

        jest.advanceTimersByTime(400);
        expect(store.sidebars[0].active).toBe(true);
    });
});
