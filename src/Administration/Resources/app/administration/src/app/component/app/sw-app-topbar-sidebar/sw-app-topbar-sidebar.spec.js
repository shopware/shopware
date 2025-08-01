/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-app-topbar-sidebar', { sync: true }), {
        global: {
            stubs: {
                'sw-context-menu-item': true,
                'sw-context-button': true,
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

describe('sw-app-topbar-sidebar', () => {
    let wrapper = null;

    it('should render button correctly', async () => {
        const store = Shopware.Store.get('sidebar');
        store.sidebars.push(sidebar);

        wrapper = await createWrapper();

        const button = wrapper.find('button');
        expect(button.classes()).toContain('sw-app-topbar-sidebar__icon');
    });
});
