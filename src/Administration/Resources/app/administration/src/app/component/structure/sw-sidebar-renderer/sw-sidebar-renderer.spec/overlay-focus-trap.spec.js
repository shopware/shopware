import { mount } from '@vue/test-utils';
import { ui } from '@shopware-ag/meteor-admin-sdk';
import initializeSidebar from 'src/app/init/sidebar.init';

describe('src/app/component/structure/sw-sidebar-renderer: overlay focus trap', () => {
    let mockLocalStorage;

    // Narrow enough that the default sidebar width does not fit next to the
    // main content, so a resizable sidebar opens straight into overlay mode.
    const OVERLAY_PAGE_WIDTH = 1400;

    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-sidebar-renderer', {
                sync: true,
            }),
            {
                global: {
                    stubs: {
                        'sw-iframe-renderer': true,
                        'mt-icon': true,
                        'mt-button': true,
                    },
                    provide: {},
                },
                attachTo: document.body,
            },
        );
    }

    async function createOverlayWrapper({ resizable = true } = {}) {
        const wrapper = await createWrapper();

        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
            resizable,
        });
        Shopware.Store.get('sidebar').setActiveSidebar('test-sidebar');
        await flushPromises();

        return wrapper;
    }

    beforeAll(() => {
        initializeSidebar();

        mockLocalStorage = {
            getItem: jest.fn(),
            setItem: jest.fn(),
        };
        Object.defineProperty(window, 'localStorage', { value: mockLocalStorage });
    });

    beforeEach(() => {
        window.innerWidth = OVERLAY_PAGE_WIDTH;
        mockLocalStorage.getItem.mockReturnValue(null);

        Shopware.Store.get('sidebar').sidebars = [];

        Shopware.Store.get('extensions').extensionsState = {};
        Shopware.Store.get('extensions').addExtension({
            name: 'jestapp',
            baseUrl: '',
            permissions: [],
            version: '1.0.0',
            type: 'app',
            integrationId: '123',
            active: true,
        });
    });

    it('should trap the focus inside the panel in overlay mode', async () => {
        const wrapper = await createOverlayWrapper();

        expect(wrapper.vm.focusTrap).toBeTruthy();

        const panel = wrapper.find('.sw-sidebar-renderer.is-active');
        expect(panel.attributes('role')).toBe('dialog');
        expect(panel.attributes('aria-modal')).toBe('true');

        // The stubbed panel has no tabbable nodes in jsdom, so the trap
        // falls back to the container itself.
        expect(document.activeElement).toBe(panel.element);

        wrapper.unmount();
    });

    it('should not trap the focus in docked mode', async () => {
        window.innerWidth = 2600;

        const wrapper = await createOverlayWrapper();

        expect(wrapper.vm.sidebarDisplayOptions.isOverlayMode).toBe(false);
        expect(wrapper.vm.focusTrap).toBeNull();
        expect(wrapper.find('.sw-sidebar-renderer.is-active').attributes('role')).toBeUndefined();

        wrapper.unmount();
    });

    it('should not trap the focus for a non-resizable sidebar', async () => {
        const wrapper = await createOverlayWrapper({ resizable: false });

        expect(wrapper.vm.focusTrap).toBeNull();
        expect(wrapper.find('.sw-sidebar-renderer.is-active').attributes('role')).toBeUndefined();

        wrapper.unmount();
    });

    it('should release the trap without closing the sidebar when overlay mode ends', async () => {
        const wrapper = await createOverlayWrapper();

        expect(wrapper.vm.focusTrap).toBeTruthy();

        window.innerWidth = 2600;
        window.dispatchEvent(new Event('resize'));
        await flushPromises();

        expect(wrapper.vm.focusTrap).toBeNull();
        expect(Shopware.Store.get('sidebar').sidebars[0].active).toBe(true);
        expect(Shopware.Store.get('sidebar').closingSidebar).toBeNull();
        expect(wrapper.find('.sw-sidebar-renderer.is-active').attributes('role')).toBeUndefined();

        wrapper.unmount();
    });

    it('should close the sidebar on Escape', async () => {
        const wrapper = await createOverlayWrapper();

        expect(wrapper.vm.focusTrap).toBeTruthy();

        jest.useFakeTimers();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(wrapper.vm.focusTrap).toBeNull();
        expect(Shopware.Store.get('sidebar').closingSidebar).toBe('test-sidebar');

        jest.advanceTimersByTime(400);

        expect(Shopware.Store.get('sidebar').sidebars[0].active).toBe(false);
        expect(Shopware.Store.get('sidebar').closingSidebar).toBeNull();

        jest.useRealTimers();
        wrapper.unmount();
    });

    it('should release the trap while resizing and re-engage it afterwards', async () => {
        const wrapper = await createOverlayWrapper();

        expect(wrapper.vm.focusTrap).toBeTruthy();

        await wrapper.find('.sw-sidebar-renderer__resize-handle').trigger('mousedown', { clientX: 100 });
        await flushPromises();

        expect(wrapper.vm.focusTrap).toBeNull();

        document.dispatchEvent(new MouseEvent('mouseup'));
        await flushPromises();

        expect(wrapper.vm.focusTrap).toBeTruthy();

        wrapper.unmount();
    });

    it('should move the trap to the other panel when switching sidebars while open', async () => {
        const wrapper = await createOverlayWrapper();

        await ui.sidebar.add({
            icon: 'regular-file',
            title: 'Second sidebar',
            locationId: 'second-sidebar',
            resizable: true,
        });

        const firstTrap = wrapper.vm.focusTrap;
        expect(firstTrap).toBeTruthy();

        Shopware.Store.get('sidebar').setActiveSidebar('second-sidebar');
        await flushPromises();

        expect(wrapper.vm.focusTrap).toBeTruthy();
        expect(wrapper.vm.focusTrap).not.toBe(firstTrap);

        const panels = wrapper.findAll('.sw-sidebar-renderer');
        expect(document.activeElement).toBe(panels.at(1).element);

        wrapper.unmount();
    });
});
