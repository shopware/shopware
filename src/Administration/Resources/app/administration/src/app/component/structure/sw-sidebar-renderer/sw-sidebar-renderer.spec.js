import { mount } from '@vue/test-utils';
import { ui } from '@shopware-ag/meteor-admin-sdk';
import initializeSidebar from 'src/app/init/sidebar.init';

describe('src/app/component/structure/sw-sidebar-renderer', () => {
    let mockLocalStorage;

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
            },
        );
    }

    async function dragSidebarToWidth(wrapper, clientX) {
        const resizeHandle = wrapper.find('.sw-sidebar-renderer__resize-handle');
        expect(resizeHandle.exists()).toBe(true);

        await resizeHandle.trigger('mousedown', { clientX: 100 });

        await wrapper.vm.$nextTick();
        expect(wrapper.vm.sidebarDisplayOptions.isResizing).toBe(true);
        document.dispatchEvent(new MouseEvent('mousemove', { clientX }));
        document.dispatchEvent(new MouseEvent('mouseup'));
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.sidebarDisplayOptions.isResizing).toBe(false);
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

    it('should render no sidebar when no sidebar is active', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-sidebar-renderer').exists()).toBe(false);
    });

    it('should render sidebar when a sidebar is active', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-sidebar-renderer').exists()).toBe(false);

        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        Shopware.Store.get('sidebar').sidebars[0].active = true;

        expect(wrapper.find('.sw-sidebar-renderer').exists()).toBe(true);
    });

    it('should close sidebar when close button is clicked', async () => {
        const wrapper = await createWrapper();

        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        Shopware.Store.get('sidebar').sidebars[0].active = true;

        expect(wrapper.find('.sw-sidebar-renderer').exists()).toBe(true);

        await wrapper.find('.sw-sidebar-renderer__button-close').trigger('click');

        expect(Shopware.Store.get('sidebar').sidebars[0].active).toBe(false);
    });

    describe('resize functionality', () => {
        const PAGE_WIDTH = 1920;
        const MAIN_CONTENT_MIN_SIZE = 1400;

        beforeEach(() => {
            window.innerWidth = PAGE_WIDTH;
            mockLocalStorage.getItem.mockClear();
            mockLocalStorage.setItem.mockClear();
        });

        it('should initialize with saved width from localStorage', async () => {
            mockLocalStorage.getItem.mockReturnValue('600');

            const wrapper = await createWrapper();

            expect(wrapper.vm.sidebarDisplayOptions.currentWidth).toBe('600px');
            expect(mockLocalStorage.getItem).toHaveBeenCalledWith('sw-sidebar-width');
        });

        it('should reset width to minimum when sidebar becomes non-resizable', async () => {
            mockLocalStorage.getItem.mockReturnValue('600');

            const wrapper = await createWrapper();

            await ui.sidebar.add({
                title: 'Test sidebar',
                locationId: 'test-sidebar',
                resizable: true,
            });
            Shopware.Store.get('sidebar').sidebars[0].active = true;
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.sidebarDisplayOptions.currentWidth).toBe('600px');

            Shopware.Store.get('sidebar').sidebars[0].resizable = false;
            await wrapper.vm.$forceUpdate();
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.sidebarDisplayOptions.currentWidth).toBe('480px');
            expect(mockLocalStorage.getItem).toHaveBeenCalledWith('sw-sidebar-width');
        });

        it('should not render handle when resizing is not allowed', async () => {
            const wrapper = await createWrapper();

            await ui.sidebar.add({
                title: 'Test sidebar',
                locationId: 'test-sidebar',
                resizable: false,
            });
            Shopware.Store.get('sidebar').sidebars[0].active = true;
            await wrapper.vm.$nextTick();

            const resizeHandle = wrapper.find('.sw-sidebar-renderer__resize-handle');

            expect(resizeHandle.exists()).toBe(false);

            expect(wrapper.vm.sidebarDisplayOptions.isResizing).toBe(false);
            expect(document.body.style.cursor).toBe('');
        });

        it('should not execute startSidebarResize when sidebar is not resizable', async () => {
            const wrapper = await createWrapper();

            await ui.sidebar.add({
                title: 'Test sidebar',
                locationId: 'test-sidebar',
                resizable: false,
            });
            Shopware.Store.get('sidebar').sidebars[0].active = true;
            await wrapper.vm.$nextTick();

            wrapper.vm.startSidebarResize({ clientX: 100 });
            expect(wrapper.vm.sidebarDisplayOptions.isResizing).toBe(false);
            expect(document.body.style.cursor).toBe('');
        });

        it('should start resize when resize handle is clicked', async () => {
            const wrapper = await createWrapper();

            await ui.sidebar.add({
                title: 'Test sidebar',
                locationId: 'test-sidebar',
                resizable: true,
            });
            Shopware.Store.get('sidebar').sidebars[0].active = true;
            await wrapper.vm.$nextTick();

            const resizeHandle = wrapper.find('.sw-sidebar-renderer__resize-handle');

            await resizeHandle.trigger('mousedown', { clientX: 100 });

            expect(wrapper.vm.sidebarDisplayOptions.isResizing).toBe(true);
            expect(document.body.style.cursor).toBe('col-resize');
        });

        it('should update width during resize and save to localStorage on stop', async () => {
            const wrapper = await createWrapper();

            await ui.sidebar.add({
                title: 'Test sidebar',
                locationId: 'test-sidebar',
                resizable: true,
            });
            Shopware.Store.get('sidebar').sidebars[0].active = true;
            await wrapper.vm.$nextTick();

            await dragSidebarToWidth(wrapper, 1300);

            expect(wrapper.vm.sidebarDisplayOptions.currentWidth).toBe(`${PAGE_WIDTH - 1300}px`);
            expect(mockLocalStorage.setItem).toHaveBeenCalledWith('sw-sidebar-width', `${PAGE_WIDTH - 1300}`);
        });

        it('should determine overlay mode based on threshold', async () => {
            mockLocalStorage.getItem.mockReturnValue(null);

            const wrapper = await createWrapper();

            await ui.sidebar.add({
                title: 'Test sidebar',
                locationId: 'test-sidebar',
                resizable: true,
            });
            Shopware.Store.get('sidebar').sidebars[0].active = true;
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.sidebarDisplayOptions.isOverlayMode).toBe(false);

            await dragSidebarToWidth(wrapper, 1000);
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.sidebarDisplayOptions.isOverlayMode).toBe(true);
            expect(wrapper.vm.sidebarDisplayOptions.availableWidth).toBe(`${PAGE_WIDTH - MAIN_CONTENT_MIN_SIZE}px`);
            expect(wrapper.vm.sidebarDisplayOptions.currentWidth).toBe(`${PAGE_WIDTH - 1000}px`);
        });

        it('should handle window resizing', async () => {
            const originalAddEventListener = window.addEventListener;
            let eventListener = null;

            window.addEventListener = jest.fn((event, callback) => {
                if (event === 'resize') {
                    eventListener = callback;
                }

                originalAddEventListener.call(window, event, callback);
            });

            const wrapper = await createWrapper();

            await ui.sidebar.add({
                title: 'Test sidebar',
                locationId: 'test-sidebar',
                resizable: true,
            });
            Shopware.Store.get('sidebar').sidebars[0].active = true;
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.sidebarDisplayOptions.availableWidth).toBe(`${PAGE_WIDTH - MAIN_CONTENT_MIN_SIZE}px`);
            expect(wrapper.vm.sidebarDisplayOptions.currentWidth).toBe(`480px`);
            expect(wrapper.vm.sidebarDisplayOptions.isOverlayMode).toBe(false);
            expect(window.addEventListener).toHaveBeenCalledWith('resize', expect.any(Function));
            expect(eventListener).toBeDefined();

            window.innerWidth = 1400;
            eventListener();
            await wrapper.vm.$nextTick();

            expect(wrapper.vm.sidebarDisplayOptions.availableWidth).toBe(`${1400 - MAIN_CONTENT_MIN_SIZE}px`);
            expect(wrapper.vm.sidebarDisplayOptions.currentWidth).toBe(`480px`);
            expect(wrapper.vm.sidebarDisplayOptions.isOverlayMode).toBe(true);

            // Restore original method
            window.addEventListener = originalAddEventListener;
        });
    });
});
