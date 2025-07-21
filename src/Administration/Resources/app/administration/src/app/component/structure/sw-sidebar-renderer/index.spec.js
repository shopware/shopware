import { mount } from '@vue/test-utils';
import { ui } from '@shopware-ag/meteor-admin-sdk';
import initializeSidebar from 'src/app/init/sidebar.init';

describe('src/app/component/structure/sw-sidebar-renderer', () => {
    let mockRequestAnimationFrame;
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

    beforeAll(() => {
        initializeSidebar();
        
        mockRequestAnimationFrame = jest.fn(cb => setTimeout(cb, 16));
        global.requestAnimationFrame = mockRequestAnimationFrame;
        global.cancelAnimationFrame = jest.fn();
        
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

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
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
        beforeEach(() => {
            mockLocalStorage.getItem.mockClear();
            mockLocalStorage.setItem.mockClear();
            mockRequestAnimationFrame.mockClear();
        });

        it('should initialize with saved width from localStorage', async () => {
            mockLocalStorage.getItem.mockReturnValue('600');
            
            const wrapper = await createWrapper();
            
            expect(wrapper.vm.sidebarWidth).toBe(600);
            expect(mockLocalStorage.getItem).toHaveBeenCalledWith('sw-sidebar-width');
        });

        it('should start resize when resize handle is clicked', async () => {
            const wrapper = await createWrapper();
            
            await ui.sidebar.add({
                title: 'Test sidebar',
                locationId: 'test-sidebar',
            });
            Shopware.Store.get('sidebar').sidebars[0].active = true;
            await wrapper.vm.$nextTick();

            const resizeHandle = wrapper.find('.sw-sidebar-renderer__resize-handle');
            
            Element.prototype.getBoundingClientRect = jest.fn(() => ({
                right: 500,
            }));

            await resizeHandle.trigger('mousedown', { clientX: 100 });
            
            expect(wrapper.vm.isResizing).toBe(true);
            expect(document.body.style.cursor).toBe('col-resize');
        });

        it('should update width during resize and save to localStorage on stop', async () => {
            const wrapper = await createWrapper();
            
            await ui.sidebar.add({
                title: 'Test sidebar',
                locationId: 'test-sidebar',
            });
            Shopware.Store.get('sidebar').sidebars[0].active = true;
            await wrapper.vm.$nextTick();

            const mockElement = document.createElement('div');
            mockElement.getBoundingClientRect = jest.fn(() => ({ right: 700 }));
            document.querySelector = jest.fn(() => mockElement);

            await wrapper.vm.startResize({ preventDefault: jest.fn(), clientX: 100 });
            
            expect(wrapper.vm.isResizing).toBe(true);
            
            const mouseMoveEvent = new MouseEvent('mousemove', { clientX: 200 });
            document.dispatchEvent(mouseMoveEvent);
            
            expect(mockRequestAnimationFrame).toHaveBeenCalled();
            
            const rafCallback = mockRequestAnimationFrame.mock.calls[0][0];
            rafCallback();
            
            expect(wrapper.vm.sidebarWidth).toBe(500);
            
            const mouseUpEvent = new MouseEvent('mouseup');
            document.dispatchEvent(mouseUpEvent);
            
            expect(wrapper.vm.isResizing).toBe(false);
            expect(mockLocalStorage.setItem).toHaveBeenCalledWith('sw-sidebar-width', '500');
        });

        it('should determine overlay mode based on threshold', async () => {
            mockLocalStorage.getItem.mockReturnValue(null);
            
            const wrapper = await createWrapper();
            
            expect(wrapper.vm.isOverlayMode).toBe(false);
            
            wrapper.vm.sidebarWidth = 600;
            await wrapper.vm.$nextTick();
            
            expect(wrapper.vm.isOverlayMode).toBe(true);
        });
    });
});
