/**
 * @sw-package framework
 */

import { DOMWrapper, mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-app-topbar-sidebar', { sync: true }), {
        attachTo: document.body,
    });
}

function menuItems() {
    return new DOMWrapper(document.body).findAll('.mt-action-menu-item');
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
    let store;

    beforeEach(() => {
        store = Shopware.Store.get('sidebar');
        store.sidebars = [];
        store.closingSidebar = null;
    });

    afterEach(() => {
        wrapper?.unmount();
        document.body.innerHTML = '';
        jest.restoreAllMocks();
    });

    it('should render a single sidebar as a plain toggle button', async () => {
        store.sidebars.push({ ...sidebar });

        wrapper = await createWrapper();

        const button = wrapper.find('button');
        expect(button.classes()).toContain('sw-app-topbar-sidebar__icon');
    });

    it('should show the tooltip on keyboard focus', async () => {
        store.sidebars.push({ ...sidebar });

        wrapper = await createWrapper();

        const button = wrapper.find('button');
        jest.spyOn(button.element, 'matches').mockImplementation((selector) => selector === ':focus-visible');

        await button.trigger('focus');
        await flushPromises();

        expect(document.querySelector('[role="tooltip"]').parentElement.style.display).not.toBe('none');
    });

    it('should not show the tooltip when the closing sidebar restores focus to the button', async () => {
        store.sidebars.push({ ...sidebar });

        wrapper = await createWrapper();

        const button = wrapper.find('button');
        jest.spyOn(button.element, 'matches').mockImplementation(() => false);

        await button.trigger('focus');
        await flushPromises();

        expect(document.querySelector('[role="tooltip"]').parentElement.style.display).toBe('none');
    });

    it('should render an action menu with all sidebars when multiple are registered', async () => {
        store.sidebars.push({ ...sidebar }, { ...secondSidebar });

        wrapper = await createWrapper();

        expect(menuItems()).toHaveLength(0);

        await wrapper.find('button.sw-app-topbar-sidebar__icon').trigger('click');
        await flushPromises();

        const items = menuItems();
        expect(items).toHaveLength(2);
        expect(items.at(0).text()).toBe('Example Sidebar');
        expect(items.at(1).text()).toBe('Second Sidebar');
    });

    it('should activate the selected sidebar from the action menu', async () => {
        store.sidebars.push({ ...sidebar }, { ...secondSidebar });
        const setActiveSidebar = jest.spyOn(store, 'setActiveSidebar');

        wrapper = await createWrapper();

        await wrapper.find('button.sw-app-topbar-sidebar__icon').trigger('click');
        await flushPromises();

        await menuItems().at(1).trigger('click');

        expect(setActiveSidebar).toHaveBeenCalledWith('second-location-id');
    });

    it('should open the sidebar when clicking the button while it is closed', async () => {
        store.sidebars.push({ ...sidebar, active: false });
        const setActiveSidebar = jest.spyOn(store, 'setActiveSidebar');
        const requestCloseSidebar = jest.spyOn(store, 'requestCloseSidebar');

        wrapper = await createWrapper();

        const button = wrapper.find('button');
        expect(button.attributes('aria-expanded')).toBe('false');
        expect(button.attributes('aria-label')).toBe('sidebar.ariaLabelButtonOpen');

        await button.trigger('click');

        expect(setActiveSidebar).toHaveBeenCalledWith('example-location-id');
        expect(requestCloseSidebar).not.toHaveBeenCalled();
    });

    it('should request the animated close when clicking the button while the sidebar is open', async () => {
        store.sidebars.push({ ...sidebar, active: true });
        const setActiveSidebar = jest.spyOn(store, 'setActiveSidebar');
        const requestCloseSidebar = jest.spyOn(store, 'requestCloseSidebar');

        wrapper = await createWrapper();

        const button = wrapper.find('button');
        expect(button.attributes('aria-expanded')).toBe('true');
        expect(button.attributes('aria-label')).toBe('sidebar.ariaLabelButtonClose');

        await button.trigger('click');

        expect(requestCloseSidebar).toHaveBeenCalledWith('example-location-id');
        expect(setActiveSidebar).not.toHaveBeenCalled();
    });

    it('should reopen the sidebar when clicking the button while it is closing', async () => {
        store.sidebars.push({ ...sidebar, active: true });
        store.closingSidebar = 'example-location-id';
        const setActiveSidebar = jest.spyOn(store, 'setActiveSidebar');
        const requestCloseSidebar = jest.spyOn(store, 'requestCloseSidebar');

        wrapper = await createWrapper();

        await wrapper.find('button').trigger('click');

        expect(setActiveSidebar).toHaveBeenCalledWith('example-location-id');
        expect(requestCloseSidebar).not.toHaveBeenCalled();
    });
});
