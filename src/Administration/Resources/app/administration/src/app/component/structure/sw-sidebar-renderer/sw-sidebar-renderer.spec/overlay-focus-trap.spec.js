import { mount } from '@vue/test-utils';
import { ui } from '@shopware-ag/meteor-admin-sdk';
import initializeSidebar from 'src/app/init/sidebar.init';

describe('src/app/component/structure/sw-sidebar-renderer: overlay focus trap', () => {
    let mockLocalStorage;
    let outsideButton;

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
                    },
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

    function focusIsTrappedInside(panelElement) {
        outsideButton.focus();

        return panelElement.contains(document.activeElement);
    }

    beforeAll(() => {
        initializeSidebar();

        // focus-trap's tabbable check requires layout boxes, which jsdom does
        // not compute — pretend every element is visible.
        HTMLElement.prototype.getClientRects = () => [
            { width: 10, height: 10 },
        ];

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
        Shopware.Store.get('sidebar').closingSidebar = null;
        Shopware.Store.get('sidebar').switchedWhileOpen = false;

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

        outsideButton = document.createElement('button');
        document.body.appendChild(outsideButton);
    });

    afterEach(() => {
        outsideButton.remove();
    });

    it('should trap the focus inside the panel in overlay mode', async () => {
        const wrapper = await createOverlayWrapper();

        const panel = wrapper.find('.sw-sidebar-renderer.is-active');
        expect(panel.attributes('role')).toBe('dialog');
        expect(panel.attributes('aria-modal')).toBe('true');

        // Initial focus moves into the panel, focusing an element outside
        // pulls the focus back in.
        expect(panel.element.contains(document.activeElement)).toBe(true);
        expect(focusIsTrappedInside(panel.element)).toBe(true);

        wrapper.unmount();
    });

    it('should return the focus to the previously focused element when the panel unmounts', async () => {
        outsideButton.focus();

        const wrapper = await createOverlayWrapper();
        expect(outsideButton.contains(document.activeElement)).toBe(false);

        wrapper.unmount();

        // focus-trap returns the focus in a zero-delay timeout.
        await new Promise((resolve) => {
            setTimeout(resolve, 0);
        });

        expect(document.activeElement).toBe(outsideButton);
    });

    it('should not trap the focus in docked mode', async () => {
        window.innerWidth = 2600;

        const wrapper = await createOverlayWrapper();

        const panel = wrapper.find('.sw-sidebar-renderer.is-active');
        expect(panel.attributes('role')).toBeUndefined();
        expect(focusIsTrappedInside(panel.element)).toBe(false);

        wrapper.unmount();
    });

    it('should render a non-resizable sidebar docked and without a trap', async () => {
        const wrapper = await createOverlayWrapper({ resizable: false });

        // Non-resizable sidebars always use the default width and therefore
        // never enter overlay mode, regardless of the window width.
        expect(wrapper.vm.sidebarDisplayOptions.isOverlayMode).toBe(false);

        const panel = wrapper.find('.sw-sidebar-renderer.is-active');
        expect(panel.attributes('role')).toBeUndefined();
        expect(focusIsTrappedInside(panel.element)).toBe(false);

        wrapper.unmount();
    });

    it('should release the trap without closing the sidebar when overlay mode ends', async () => {
        const wrapper = await createOverlayWrapper();

        window.innerWidth = 2600;
        window.dispatchEvent(new Event('resize'));
        await flushPromises();

        const panel = wrapper.find('.sw-sidebar-renderer.is-active');
        expect(panel.attributes('role')).toBeUndefined();
        expect(focusIsTrappedInside(panel.element)).toBe(false);
        expect(Shopware.Store.get('sidebar').sidebars[0].active).toBe(true);
        expect(Shopware.Store.get('sidebar').closingSidebar).toBeNull();

        wrapper.unmount();
    });

    it('should close the sidebar on Escape', async () => {
        const wrapper = await createOverlayWrapper();

        jest.useFakeTimers();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

        expect(Shopware.Store.get('sidebar').closingSidebar).toBe('test-sidebar');

        jest.advanceTimersByTime(400);

        expect(Shopware.Store.get('sidebar').sidebars[0].active).toBe(false);
        expect(Shopware.Store.get('sidebar').closingSidebar).toBeNull();

        jest.useRealTimers();
        wrapper.unmount();
    });

    it('should pause the trap while resizing and resume it without moving the focus', async () => {
        const wrapper = await createOverlayWrapper();

        const panel = wrapper.find('.sw-sidebar-renderer.is-active');
        const closeButton = wrapper.find('.sw-sidebar-renderer__button-close');
        closeButton.element.focus();

        await wrapper.find('.sw-sidebar-renderer__resize-handle').trigger('mousedown', { clientX: 100 });
        await flushPromises();

        // Paused: focus may leave the panel while dragging.
        expect(focusIsTrappedInside(panel.element)).toBe(false);

        closeButton.element.focus();
        document.dispatchEvent(new MouseEvent('mouseup'));
        await flushPromises();

        // Resumed without re-running the initial focus.
        expect(document.activeElement).toBe(closeButton.element);
        expect(focusIsTrappedInside(panel.element)).toBe(true);

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

        Shopware.Store.get('sidebar').setActiveSidebar('second-sidebar');
        await flushPromises();

        const panels = wrapper.findAll('.sw-sidebar-renderer');
        expect(panels[1].element.contains(document.activeElement)).toBe(true);
        expect(focusIsTrappedInside(panels[1].element)).toBe(true);

        wrapper.unmount();
    });
});
