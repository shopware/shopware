/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-app-topbar-sidebar', { sync: true }), {
        global: {
            stubs: {
                'mt-tooltip': {
                    template: '<div class="mt-tooltip"><slot /></div>',
                },
                'mt-dropdown-menu-root': {
                    template: '<div class="mt-dropdown-menu-root"><slot /></div>',
                },
                'mt-dropdown-menu-trigger': {
                    template: '<div class="mt-dropdown-menu-trigger"><slot /></div>',
                },
                'mt-dropdown-menu-portal': {
                    template: '<div class="mt-dropdown-menu-portal"><slot /></div>',
                },
                'mt-action-menu': {
                    template: '<div class="mt-action-menu"><slot /></div>',
                },
                'mt-action-menu-item': {
                    template: '<button class="mt-action-menu-item" @click="$emit(\'select\')"><slot /></button>',
                    emits: ['select'],
                },
            },
        },
    });
}

const sidebar = {
    locationId: 'example-location-id',
    title: 'Example Sidebar',
    icon: 'regular-file',
    baseUrl: 'https://example.com',
    active: false,
};

const secondSidebar = {
    locationId: 'second-location-id',
    title: 'Second Sidebar',
    icon: 'regular-star',
    baseUrl: 'https://example.com/second',
    active: false,
};

describe('sw-app-topbar-sidebar', () => {
    let wrapper = null;

    beforeEach(() => {
        Shopware.Store.get('sidebar').sidebars = [];
    });

    it('should render button correctly', async () => {
        const store = Shopware.Store.get('sidebar');
        store.sidebars.push(sidebar);

        wrapper = await createWrapper();

        const button = wrapper.find('button');
        expect(button.classes()).toContain('sw-app-topbar-sidebar__icon');
    });

    it('should render an action menu with all sidebars when multiple are registered', async () => {
        const store = Shopware.Store.get('sidebar');
        store.sidebars.push(sidebar, secondSidebar);

        wrapper = await createWrapper();

        const trigger = wrapper.find('.mt-dropdown-menu-trigger button');
        expect(trigger.classes()).toContain('sw-app-topbar-sidebar__icon');

        const items = wrapper.findAll('.mt-action-menu-item');
        expect(items).toHaveLength(2);
        expect(items[0].text()).toBe('Example Sidebar');
        expect(items[1].text()).toBe('Second Sidebar');
    });

    it('should activate the selected sidebar from the action menu', async () => {
        const store = Shopware.Store.get('sidebar');
        store.sidebars.push(sidebar, secondSidebar);
        store.setActiveSidebar = jest.fn();

        wrapper = await createWrapper();

        const items = wrapper.findAll('.mt-action-menu-item');
        await items[1].trigger('click');

        expect(store.setActiveSidebar).toHaveBeenCalledWith('second-location-id');
    });
});
