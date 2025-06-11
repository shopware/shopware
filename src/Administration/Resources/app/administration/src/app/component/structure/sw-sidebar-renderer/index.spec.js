import { mount } from '@vue/test-utils';
import { ui } from '@shopware-ag/meteor-admin-sdk';
import initializeSidebar from 'src/app/init/sidebar.init';

describe('src/app/component/structure/sw-sidebar-renderer', () => {
    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-sidebar-renderer', {
                sync: true,
            }),
            {
                global: {
                    stubs: {
                        'sw-iframe-renderer': true,
                    },
                    provide: {},
                },
            },
        );
    }

    beforeAll(() => {
        // Start initalizer
        initializeSidebar();
    });

    beforeEach(() => {
        // Reset the sidebar store
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

        // Check that sidebar does not exist
        expect(wrapper.find('.sw-sidebar-renderer').exists()).toBe(false);

        // Create a sidebar
        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        // Make the sidebar active
        Shopware.Store.get('sidebar').sidebars[0].active = true;

        // Check that the sidebar is rendered
        expect(wrapper.find('.sw-sidebar-renderer').exists()).toBe(true);
    });

    it('should close sidebar when close button is clicked', async () => {
        const wrapper = await createWrapper();

        // Create a sidebar
        await ui.sidebar.add({
            icon: 'regular-star',
            title: 'Test sidebar',
            locationId: 'test-sidebar',
        });

        // Make the sidebar active
        Shopware.Store.get('sidebar').sidebars[0].active = true;

        // Check that the sidebar is rendered
        expect(wrapper.find('.sw-sidebar-renderer').exists()).toBe(true);

        // Click the close button
        await wrapper.find('.sw-sidebar-renderer__button-close').trigger('click');

        // Check that the sidebar is closed
        expect(Shopware.Store.get('sidebar').sidebars[0].active).toBe(false);
    });
});
